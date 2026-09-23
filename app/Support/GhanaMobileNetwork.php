<?php

namespace App\Support;

/**
 * Ghana mobile network helper — detects the network from a phone's prefix and validates
 * agent bundle orders before any money moves. A slim, static port of Databundleshub's
 * GhanaMobileNetwork (its NetworkRegistry is DB-backed; DataSite only needs the fixed
 * prefix/size rules). Network codes match our tier_prices: mtn / telecel / at.
 */
final class GhanaMobileNetwork
{
    public const MTN = 'mtn';

    public const TELECEL = 'telecel';

    public const AT = 'at';

    /**
     * Fixed discrete GB packages agents may purchase across networks.
     * Matches upstream Databundleshub provider allowlist.
     *
     * @var list<int>
     */
    public const MTN_PACKAGE_SIZES_GB = [1, 2, 3, 4, 5, 6, 8, 10, 15, 20, 25, 30, 40, 50];

    public const TELECEL_PACKAGE_SIZES_GB = [10, 15, 20, 30, 50, 100];

    public const AT_PACKAGE_SIZES_GB = [1, 2, 3, 5, 10, 15, 20, 30, 50, 100];

    /**
     * @var array<string, array{label: string, prefixes: list<string>, min_gb: int, max_gb: int}>
     */
    private const NETWORKS = [
        self::MTN => ['label' => 'MTN', 'prefixes' => ['024', '025', '053', '054', '055', '059'], 'min_gb' => 1, 'max_gb' => 50],
        self::TELECEL => ['label' => 'Telecel', 'prefixes' => ['020', '050'], 'min_gb' => 10, 'max_gb' => 100],
        self::AT => ['label' => 'AirtelTigo', 'prefixes' => ['026', '027', '056', '057'], 'min_gb' => 1, 'max_gb' => 100],
    ];

    /**
     * Normalize any Ghanaian input (233…, 9-digit, spaced) to a 10-digit 0XXXXXXXXX form,
     * or '' when it is not a valid mobile number.
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 3);
        } elseif (strlen($digits) === 9 && in_array($digits[0], ['2', '5'], true)) {
            $digits = '0' . $digits;
        }

        return strlen($digits) === 10 && $digits[0] === '0' ? $digits : '';
    }

    /**
     * Resolve the network code (mtn/telecel/at) from a phone number, or null if unknown.
     */
    public static function detect(string $phone): ?string
    {
        $normalized = self::normalize($phone);
        if ($normalized === '') {
            return null;
        }

        $prefix = substr($normalized, 0, 3);
        foreach (self::NETWORKS as $code => $definition) {
            if (in_array($prefix, $definition['prefixes'], true)) {
                return $code;
            }
        }

        return null;
    }

    public static function label(?string $code): ?string
    {
        return $code !== null && isset(self::NETWORKS[$code]) ? self::NETWORKS[$code]['label'] : null;
    }

    /**
     * Canonical display order of the networks: MTN, Telecel, AirtelTigo.
     *
     * @return list<string>
     */
    public static function order(): array
    {
        return array_keys(self::NETWORKS);
    }

    /**
     * Upstream Databundleshub API identifier for each network.
     */
    public static function apiCode(string $code): string
    {
        return match ($code) {
            self::MTN => 'YELLO',
            self::TELECEL => 'TELECEL',
            self::AT => 'AIRTELTIGO',
            default => strtoupper($code),
        };
    }

    /**
     * Network reference data for the frontend: prefix table (for live detection) and the
     * allowed package sizes per network. Keeps the single source of truth on the server.
     *
     * @return list<array{code: string, api_code: string, label: string, prefixes: list<string>, sizes: list<int>, min_gb: int, max_gb: int}>
     */
    public static function meta(): array
    {
        $meta = [];
        foreach (self::NETWORKS as $code => $definition) {
            $meta[] = [
                'code' => $code,
                'api_code' => self::apiCode($code),
                'label' => $definition['label'],
                'prefixes' => $definition['prefixes'],
                'sizes' => self::packageSizesGb($code),
                'min_gb' => $definition['min_gb'],
                'max_gb' => $definition['max_gb'],
            ];
        }

        return $meta;
    }

    /**
     * The fixed GB packages an agent may order on a network.
     * Matches upstream provider allowlist.
     *
     * @return list<int>
     */
    public static function packageSizesGb(string $code): array
    {
        return match ($code) {
            self::MTN => self::MTN_PACKAGE_SIZES_GB,
            self::TELECEL => self::TELECEL_PACKAGE_SIZES_GB,
            self::AT => self::AT_PACKAGE_SIZES_GB,
            default => [],
        };
    }

    /**
     * Validate an agent order (phone + size) before pricing/checkout. Returns a human error
     * message, or null when the order is valid.
     */
    public static function validateOrder(string $phone, int $sizeGb): ?string
    {
        $code = self::detect($phone);
        if ($code === null) {
            return 'Unrecognized number. Use a valid MTN, Telecel, or AirtelTigo line.';
        }

        $allowed = self::packageSizesGb($code);
        if (! in_array($sizeGb, $allowed, true)) {
            $label = self::label($code);

            return "Invalid {$label} package. Allowed sizes: " . implode(', ', $allowed) . ' GB.';
        }

        return null;
    }
}
