<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfReportExporter
{
    /** @param array<int, string> $headers
     * @param  iterable<array<int, int|float|string|null>>  $rows
     */
    public function download(string $filename, string $title, string $subtitle, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($title, $subtitle, $headers, $rows): void {
            $stream = fopen('php://temp/maxmemory:5242880', 'w+b');
            if ($stream === false) {
                throw new \RuntimeException('Unable to create a temporary PDF export stream.');
            }

            $this->writeDocument($stream, $title, $subtitle, $headers, $rows);
            rewind($stream);
            fpassthru($stream);
            fclose($stream);
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function pageHeader(string $title, string $subtitle): string
    {
        return $this->text($title, 36, 558, 16)
            .$this->text($subtitle, 36, 540, 9)
            .$this->text('Generated '.now()->format('Y-m-d H:i'), 650, 558, 8)
            .'0.78 0.80 0.78 RG 36 532 m 806 532 l S 0 0 0 rg'."\n";
    }

    private function text(string $value, int $x, int $y, int $fontSize): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded === false ? '' : $encoded);

        return "BT /F1 {$fontSize} Tf {$x} {$y} Td ({$escaped}) Tj ET\n";
    }

    /** @return array<int, string> */
    private function wrap(string $value, int $limit): array
    {
        return explode("\n", wordwrap($value, $limit, "\n", true));
    }

    /** @param resource $stream
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, int|float|string|null>>  $rows
     */
    private function writeDocument($stream, string $title, string $subtitle, array $headers, iterable $rows): void
    {
        fwrite($stream, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n");
        $offsets = [1 => ftell($stream)];
        fwrite($stream, "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");
        $offsets[3] = ftell($stream);
        fwrite($stream, "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n");

        $pageIds = [];
        $pageIndex = 0;
        $commands = $this->pageHeader($title, $subtitle);
        $y = 520;
        $rowCount = 0;

        foreach ($rows as $row) {
            $details = [];
            foreach ($headers as $index => $header) {
                $details[] = $header.': '.(string) ($row[$index] ?? '');
            }

            foreach ($this->wrap(implode('  |  ', $details), 128) as $line) {
                if ($y < 55) {
                    $this->writePage($stream, $pageIndex++, $commands, $offsets, $pageIds);
                    $commands = $this->pageHeader($title, $subtitle);
                    $y = 520;
                }
                $commands .= $this->text($line, 36, $y, 7);
                $y -= 12;
            }
            $commands .= '0.82 0.84 0.82 RG 36 '.($y + 4).' m 806 '.($y + 4).' l S 0 0 0 rg'."\n";
            $y -= 8;
            $rowCount++;
        }

        if ($rowCount === 0) {
            $commands .= $this->text('No records match these filters.', 36, $y, 10);
        }
        $this->writePage($stream, $pageIndex, $commands, $offsets, $pageIds);

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

    /** @param resource $stream
     * @param  array<int, int>  $offsets
     * @param  array<int, string>  $pageIds
     */
    private function writePage($stream, int $index, string $content, array &$offsets, array &$pageIds): void
    {
        $pageId = 4 + ($index * 2);
        $contentId = $pageId + 1;
        $pageIds[] = $pageId.' 0 R';
        $offsets[$pageId] = ftell($stream);
        fwrite($stream, $pageId." 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>\nendobj\n");
        $offsets[$contentId] = ftell($stream);
        fwrite($stream, $contentId.' 0 obj'."\n<< /Length ".strlen($content)." >>\nstream\n".$content."endstream\nendobj\n");
    }
}
