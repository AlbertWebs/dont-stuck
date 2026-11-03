<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelXmlExporter
{
    /**
     * @param  array<string, array{headers: array<int, string>, rows: iterable<array<int, int|float|string|null>>}>  $sheets
     */
    public function downloadWorkbook(string $filename, array $sheets): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheets): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';

            foreach ($sheets as $name => $sheet) {
                $safeName = mb_substr(preg_replace('/[\\[\\]:*?\\/\\\\]/u', ' ', $name) ?: 'Sheet', 0, 31);
                echo '<Worksheet ss:Name="'.htmlspecialchars($safeName, ENT_XML1 | ENT_QUOTES, 'UTF-8').'\"><Table>';
                $this->writeRow($sheet['headers']);

                foreach ($sheet['rows'] as $row) {
                    $this->writeRow($row);
                }

                echo '</Table></Worksheet>';
            }

            echo '</Workbook>';
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    /** @param array<int, string> $headers
     * @param  iterable<array<int, int|float|string|null>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Report"><Table>';
            $this->writeRow($headers);
            foreach ($rows as $row) {
                $this->writeRow($row);
            }
            echo '</Table></Worksheet></Workbook>';
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    /** @param array<int, int|float|string|null> $values */
    private function writeRow(array $values): void
    {
        echo '<Row>';
        foreach ($values as $value) {
            $text = (string) ($value ?? '');
            $type = is_numeric($value) && preg_match('/^-?\d+(?:\.\d+)?$/', $text) === 1 ? 'Number' : 'String';
            echo '<Cell><Data ss:Type="'.$type.'">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Data></Cell>';
        }
        echo '</Row>';
    }
}
