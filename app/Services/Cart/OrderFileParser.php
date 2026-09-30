<?php

namespace App\Services\Cart;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads an uploaded orders sheet into [phone, sizeGb, network] rows for the cart. Columns are located
 * by HEADER NAME (receiver/phone, capacity/size, network) so the blank template, a bare phone+size
 * sheet, AND the full admin export (extra columns ignored) all upload. With no recognizable header it
 * falls back to positional A=phone, B=size, C=network. Network is optional (blank → prefix detection).
 */
class OrderFileParser
{
    /** Header cell → the field it maps to. Matched case-insensitively; first match wins. */
    private const PHONE_HEADERS = ['receiver', 'phone', 'phone number', 'phonenumber', 'beneficiary', 'number', 'msisdn'];

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $path = (string) $file->getRealPath();

        $raw = match ($extension) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx', 'xls' => $this->readSpreadsheet($path),
            default => [],
        };

        return $this->mapRows($raw);
    }

    /**
     * @param  list<list<string>>  $raw
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function mapRows(array $raw): array
    {
        if ($raw === []) {
            return [];
        }

        $map = $this->headerMap($raw[0]);
        if ($map !== null) {
            $dataRows = array_slice($raw, 1);
        } else {
            // No header row: read positionally, but drop a leading non-phone label row if present.
            $map = ['phone' => 0, 'size' => 1, 'network' => 2];
            $dataRows = $this->looksLikePhone($raw[0][0] ?? '') ? $raw : array_slice($raw, 1);
        }

        $rows = [];
        foreach ($dataRows as $r) {
            $network = $map['network'] !== null ? trim((string) ($r[$map['network']] ?? '')) : '';
            $rows[] = [
                trim((string) ($r[$map['phone']] ?? '')),
                trim((string) ($r[$map['size']] ?? '')),
                $network,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $cells
     * @return array{phone: int, size: int, network: int|null}|null
     */
    private function headerMap(array $cells): ?array
    {
        $phone = $size = $network = null;
        foreach ($cells as $i => $cell) {
            $key = strtolower(trim((string) $cell));
            if ($phone === null && in_array($key, self::PHONE_HEADERS, true)) {
                $phone = $i;
            } elseif ($size === null && (str_contains($key, 'capacity') || str_contains($key, 'size') || $key === 'gb' || str_contains($key, 'data'))) {
                $size = $i;
            } elseif ($network === null && $key === 'network') {
                $network = $i;
            }
        }

        return $phone !== null && $size !== null ? ['phone' => $phone, 'size' => $size, 'network' => $network] : null;
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_map(static fn ($cell): string => (string) $cell, $data);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function readSpreadsheet(string $path): array
    {
        $data = IOFactory::load($path)->getActiveSheet()->toArray();

        return array_map(
            static fn (array $row): array => array_map(static fn ($cell): string => (string) $cell, $row),
            $data,
        );
    }

    private function looksLikePhone(string $firstCell): bool
    {
        return (bool) preg_match('/^0?\d{9,12}$/', preg_replace('/\D+/', '', $firstCell) ?? '');
    }
}
