<?php

namespace App\Services\Cart;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads an uploaded orders sheet into [phone, sizeGb] rows for the cart. Column A = phone,
 * Column B = size in GB (mirrors Databundleshub's sheet format). CSV is parsed natively; XLSX/XLS
 * go through PhpSpreadsheet. A header row is skipped when its first cell is not a phone number.
 */
class OrderFileParser
{
    /**
     * @return list<array{0: string, 1: string}>
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $path = (string) $file->getRealPath();

        return match ($extension) {
            'csv', 'txt' => $this->parseCsv($path),
            'xlsx', 'xls' => $this->parseSpreadsheet($path),
            default => [],
        };
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $rows = [];
        $line = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            if ($this->isHeader($line, (string) ($data[0] ?? ''))) {
                continue;
            }
            $rows[] = [trim((string) ($data[0] ?? '')), trim((string) ($data[1] ?? ''))];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function parseSpreadsheet(string $path): array
    {
        $worksheet = IOFactory::load($path)->getActiveSheet();
        $highestRow = (int) $worksheet->getHighestRow();

        $rows = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $phone = (string) $worksheet->getCell("A{$row}")->getValue();
            if ($this->isHeader($row, $phone)) {
                continue;
            }
            $rows[] = [trim($phone), trim((string) $worksheet->getCell("B{$row}")->getValue())];
        }

        return $rows;
    }

    private function isHeader(int $rowNumber, string $firstCell): bool
    {
        return $rowNumber === 1 && ! preg_match('/^0?\d{9,12}$/', preg_replace('/\D+/', '', $firstCell) ?? '');
    }
}
