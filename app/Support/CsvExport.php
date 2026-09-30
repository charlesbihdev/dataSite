<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared CSV download for admin exports: streams a UTF-8 BOM (so Excel reads accents), the header
 * row, then each data row. `text()` wraps a value as an Excel text-literal so phone numbers keep
 * their leading 0 instead of being read as a number and mangled to 551234567 / 5.5E+09.
 */
class CsvExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function text(?string $value): string
    {
        return $value === null || $value === '' ? '' : '="'.$value.'"';
    }
}
