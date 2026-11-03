<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientRecordPdfExporter
{
    private const PAGE_WIDTH = 595;

    private const PAGE_HEIGHT = 842;

    private const LEFT = 42;

    private const RIGHT = 553;

    /**
     * @param  array<string, array{headers: array<int, string>, rows: Collection<int, array<int, int|float|string|null>>}>  $sheets
     */
    public function download(string $filename, string $borrowerName, string $nationalId, string $phone, array $sheets): StreamedResponse
    {
        $pages = $this->renderPages($borrowerName, $nationalId, $phone, $sheets);

        return response()->streamDownload(function () use ($pages): void {
            $stream = fopen('php://temp/maxmemory:5242880', 'w+b');
            if ($stream === false) {
                throw new \RuntimeException('Unable to create a temporary PDF export stream.');
            }

            $this->writeDocument($stream, $pages);
            rewind($stream);
            fpassthru($stream);
            fclose($stream);
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * @param  array<string, array{headers: array<int, string>, rows: Collection<int, array<int, int|float|string|null>>}>  $sheets
     * @return array<int, string>
     */
    private function renderPages(string $borrowerName, string $nationalId, string $phone, array $sheets): array
    {
        $generatedAt = now()->format('d M Y, H:i');
        $pages = [];
        $commands = '';
        $y = 0;
        $startPage = function () use (&$commands, &$y, $borrowerName, $nationalId, $phone, $generatedAt): void {
            $commands = '0.97 0.97 0.95 rg 0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT." re f\n";
            $commands .= $this->text('CLIENT ACCOUNT RECORD', self::LEFT, 800, 9, true, '0.43 0.12 0.14');
            $commands .= $this->text($borrowerName, self::LEFT, 772, 20, true, '0.08 0.12 0.12');
            $commands .= $this->text('National ID: '.$nationalId.'     Phone: '.$phone, self::LEFT, 752, 9, false, '0.30 0.34 0.33');
            $commands .= $this->text('Generated '.$generatedAt.'  |  CONFIDENTIAL', 380, 800, 7, false, '0.35 0.39 0.37');
            $commands .= "0.82 0.84 0.80 RG {$this->number(self::LEFT)} 738 m {$this->number(self::RIGHT)} 738 l S\n";
            $y = 720;
        };
        $finishPage = function () use (&$pages, &$commands): void {
            $commands .= '0.82 0.84 0.80 RG '.self::LEFT.' 34 m '.self::RIGHT." 34 l S\n";
            $pages[] = $commands;
        };
        $ensureSpace = function (int $needed) use (&$y, &$commands, &$pages, $startPage, $finishPage): void {
            if ($y - $needed < 55) {
                $finishPage();
                $startPage();
            }
        };

        $startPage();
        $loanRows = $sheets['Loans']['rows'] ?? collect();
        $paymentRows = $sheets['Payments']['rows'] ?? collect();
        $installmentRows = $sheets['Installments']['rows'] ?? collect();
        $activeLoans = $loanRows->filter(fn (array $row): bool => in_array(strtolower((string) ($row[2] ?? '')), ['active', 'overdue', 'defaulted'], true));
        $outstanding = $activeLoans->sum(fn (array $row): float => (float) ($row[12] ?? 0));
        $disbursed = $loanRows->sum(fn (array $row): float => (float) ($row[4] ?? 0));
        $paymentsCollected = $paymentRows->filter(fn (array $row): bool => strtolower((string) ($row[5] ?? '')) === 'posted')->sum(fn (array $row): float => (float) ($row[2] ?? 0));
        $paidInstallments = $installmentRows->filter(fn (array $row): bool => strtolower((string) ($row[13] ?? '')) === 'paid')->count();

        $commands .= $this->sectionTitle('Account at a glance', self::LEFT, $y);
        $y -= 29;
        $summary = [
            ['Loans issued', (string) $loanRows->count()],
            ['Active accounts', (string) $activeLoans->count()],
            ['Total disbursed', $this->money($disbursed)],
            ['Outstanding balance', $this->money($outstanding)],
            ['Repayments collected', $this->money($paymentsCollected)],
            ['Installments paid', $paidInstallments.' / '.$installmentRows->count()],
        ];
        $this->drawCells($commands, $y, $summary, 3, 170);
        $y -= $this->cellRowsHeight($summary, 3, 170) + 22;

        foreach ($sheets as $section => $sheet) {
            $headers = $sheet['headers'];
            $rows = $sheet['rows'];
            $isProfile = $section === 'Client profile';
            if ($section === 'Loans') {
                $section = 'Loan facilities';
            } elseif ($section === 'Payments') {
                $section = 'Repayment transactions';
            } elseif ($section === 'Activity log') {
                $section = 'Audit trail';
            }

            $firstRow = $rows->first();
            $firstPairs = $firstRow === null ? [] : ($isProfile
                ? $rows->map(fn (array $row): array => $this->formatFinancialPair((string) ($row[0] ?? 'Field'), $this->displayValue($row[1] ?? null)))->all()
                : $this->recordPairs($firstRow, $headers));
            $ensureSpace(46 + ($firstPairs === [] ? 0 : $this->cellRowsHeight($firstPairs, 2, 251) + 38));
            $commands .= $this->sectionTitle($section, self::LEFT, $y);
            $y -= 25;

            if ($rows->isEmpty()) {
                $commands .= $this->text('No records available.', self::LEFT + 2, $y, 9, false, '0.38 0.41 0.40');
                $y -= 23;

                continue;
            }

            if ($isProfile) {
                $cellHeight = $this->cellRowsHeight($firstPairs, 2, 251);
                $this->drawCells($commands, $y - 4, $firstPairs, 2, 251);
                $y -= $cellHeight + 8;

                continue;
            }

            foreach ($rows->values() as $rowIndex => $row) {
                $pairs = $this->recordPairs($row, $headers);
                if ($pairs === []) {
                    continue;
                }

                $recordTitle = count($rows) > 1 ? 'Record '.($rowIndex + 1) : null;
                if ($recordTitle !== null) {
                    $ensureSpace(28);
                    $commands .= $this->text($recordTitle, self::LEFT + 2, $y, 8, true, '0.38 0.12 0.15');
                    $y -= 13;
                }

                $cellHeight = $this->cellRowsHeight($pairs, 2, 251);
                $ensureSpace($cellHeight + 11);
                $this->drawCells($commands, $y, $pairs, 2, 251);
                $y -= $cellHeight + 8;
                $commands .= '0.88 0.89 0.86 RG '.self::LEFT.' '.($y + 3).' m '.self::RIGHT.' '.($y + 3)." l S\n";
                $y -= 6;
            }
            $y -= 8;
        }

        $finishPage();

        return $pages;
    }

    /** @param array<int, int|float|string|null> $row
     * @param  array<int, string>  $headers
     * @return array<int, array{0: string, 1: string}>
     */
    private function recordPairs(array $row, array $headers): array
    {
        $pairs = [];
        foreach ($headers as $index => $label) {
            $value = $this->displayValue($row[$index] ?? null);
            if ($value !== '') {
                $pairs[] = $this->formatFinancialPair($label, $value);
            }
        }

        return $pairs;
    }

    /** @param array<int, array{0: string, 1: string}> $pairs */
    private function cellRowsHeight(array $pairs, int $columns, int $cellWidth): int
    {
        $height = 0;
        foreach (array_chunk($pairs, $columns) as $row) {
            $rowHeight = 31;
            foreach ($row as [$label, $value]) {
                $rowHeight = max($rowHeight, 15 + (count($this->wrap($label, 34)) * 8) + (count($this->wrap($value, max(12, (int) floor($cellWidth / 5.1)))) * 10));
            }
            $height += $rowHeight + 5;
        }

        return $height;
    }

    /** @param array<int, array{0: string, 1: string}> $pairs */
    private function drawCells(string &$commands, int $top, array $pairs, int $columns, int $cellWidth): void
    {
        foreach (array_chunk($pairs, $columns) as $rowIndex => $row) {
            $rowHeight = 31;
            foreach ($row as [$label, $value]) {
                $rowHeight = max($rowHeight, 15 + (count($this->wrap($label, 34)) * 8) + (count($this->wrap($value, max(12, (int) floor($cellWidth / 5.1)))) * 10));
            }
            foreach ($row as $columnIndex => [$label, $value]) {
                $x = self::LEFT + ($columnIndex * ($cellWidth + 8));
                $commands .= "0.93 0.94 0.91 rg {$x} ".($top - $rowHeight + 2).' '.($cellWidth).' '.($rowHeight - 3)." re f\n";
                $labelY = $top - 10;
                foreach ($this->wrap($label, 34) as $line) {
                    $commands .= $this->text($line, $x + 7, $labelY, 6.5, true, '0.36 0.39 0.37');
                    $labelY -= 8;
                }
                $valueY = $labelY - 2;
                foreach ($this->wrap($value, max(12, (int) floor($cellWidth / 5.1))) as $line) {
                    $commands .= $this->text($line, $x + 7, $valueY, 8, false, '0.08 0.12 0.12');
                    $valueY -= 10;
                }
            }
            $top -= $rowHeight + 5;
        }
    }

    private function sectionTitle(string $title, int $x, int $y): string
    {
        return "0.43 0.12 0.14 rg {$x} ".($y - 5).' '.(self::RIGHT - self::LEFT).' 21 re f'."\n"
            .$this->text($title, $x + 9, $y + 1, 10, true, '1 1 1');
    }

    private function text(string $value, int $x, int $y, float $fontSize, bool $bold = false, string $color = '0 0 0'): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded === false ? '' : $encoded);
        $font = $bold ? 'F2' : 'F1';

        return "{$color} rg BT /{$font} {$fontSize} Tf {$x} {$y} Td ({$escaped}) Tj ET\n";
    }

    /** @return array<int, string> */
    private function wrap(string $value, int $limit): array
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        if ($value === '') {
            return [''];
        }

        return explode("\n", wordwrap($value, $limit, "\n", true));
    }

    private function displayValue(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return collect($decoded)->map(fn ($item, $key): string => is_array($item)
                    ? (string) $key.': '.collect($item)->map(fn ($nested, $nestedKey): string => (string) $nestedKey.' '.$this->displayValue(is_scalar($nested) ? $nested : json_encode($nested)))->implode(', ')
                    : (string) $key.': '.(is_scalar($item) ? (string) $item : json_encode($item)))->implode('; ');
            }
        }

        return (string) $value;
    }

    private function money(float $amount): string
    {
        return 'KSh '.number_format($amount, 2);
    }

    /** @return array{0: string, 1: string} */
    private function formatFinancialPair(string $label, string $value): array
    {
        $isFinancialAmount = preg_match('/amount|principal|interest|fees|penalties|outstanding|disbursed|salary|estimated value|guarantee|payout|charge/i', $label) === 1
            && preg_match('/rate|words|method/i', $label) !== 1
            && is_numeric($value);

        if (! $isFinancialAmount) {
            return [$label, $value];
        }

        if (stripos($label, 'ksh') === false) {
            $label .= ' (KSh)';
        }

        return [$label, $this->money((float) $value)];
    }

    private function number(int $value): string
    {
        return (string) $value;
    }

    /** @param resource $stream
     * @param  array<int, string>  $pages
     */
    private function writeDocument($stream, array $pages): void
    {
        fwrite($stream, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n");
        $offsets = [1 => ftell($stream)];
        fwrite($stream, "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");
        $offsets[3] = ftell($stream);
        fwrite($stream, "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n");
        $offsets[4] = ftell($stream);
        fwrite($stream, "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n");

        $pageIds = [];
        foreach ($pages as $index => $content) {
            $pageId = 5 + ($index * 2);
            $contentId = $pageId + 1;
            $pageIds[] = $pageId.' 0 R';
            $content .= $this->text('Client account record  |  Page '.($index + 1).' of '.count($pages), self::LEFT, 20, 7, false, '0.35 0.39 0.37');
            $offsets[$pageId] = ftell($stream);
            fwrite($stream, $pageId." 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ".self::PAGE_WIDTH.' '.self::PAGE_HEIGHT."] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >>\nendobj\n");
            $offsets[$contentId] = ftell($stream);
            fwrite($stream, $contentId.' 0 obj'."\n<< /Length ".strlen($content).">>\nstream\n".$content."endstream\nendobj\n");
        }

        $offsets[2] = ftell($stream);
        fwrite($stream, '2 0 obj'."\n<< /Type /Pages /Kids [".implode(' ', $pageIds).'] /Count '.count($pageIds)." >>\nendobj\n");
        ksort($offsets);
        $xrefOffset = ftell($stream);
        $maxObjectId = max(array_keys($offsets));
        fwrite($stream, "xref\n0 ".($maxObjectId + 1)."\n0000000000 65535 f \n");
        for ($id = 1; $id <= $maxObjectId; $id++) {
            fwrite($stream, sprintf("%010d 00000 n \n", $offsets[$id] ?? 0));
        }
        fwrite($stream, "trailer\n<< /Size ".($maxObjectId + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF");
    }
}
