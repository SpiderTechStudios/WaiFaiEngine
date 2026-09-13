<?php

namespace App\Support;

/**
 * Detect Tanzanian mobile-money wallets from MSISDN prefixes (TCRA numbering plan).
 *
 * Note: number portability means prefix is a best-effort signal, not a guarantee.
 */
final class NetworkDetector
{
    public const MPESA = 'Mpesa';

    public const AIRTEL_MONEY = 'AirtelMoney';

    public const HALOPESA = 'Halopesa';

    public const MIXX_BY_YAS = 'MixxByYas';

    public const TTCL = 'TTCL';

    /**
     * Local 0XX prefixes → wallet brand.
     *
     * @var array<string, string>
     */
    private const PREFIX_MAP = [
        // Vodacom → M-Pesa
        '074' => self::MPESA,
        '075' => self::MPESA,
        '076' => self::MPESA,
        '079' => self::MPESA,

        // Airtel → Airtel Money
        '068' => self::AIRTEL_MONEY,
        '069' => self::AIRTEL_MONEY,
        '078' => self::AIRTEL_MONEY,

        // Halotel (Viettel) → HaloPesa
        '061' => self::HALOPESA,
        '062' => self::HALOPESA,

        // Yas (formerly Tigo / MIC) → Mixx by Yas
        '065' => self::MIXX_BY_YAS,
        '067' => self::MIXX_BY_YAS,
        '071' => self::MIXX_BY_YAS,
        '077' => self::MIXX_BY_YAS,

        // TTCL
        '073' => self::TTCL,
    ];

    /**
     * Detect wallet brand from a phone number, or null if unknown / invalid.
     */
    public static function detect(?string $phone): ?string
    {
        $national = self::normalizeNational($phone);

        if ($national === null) {
            return null;
        }

        $prefix = substr($national, 0, 3);

        return self::PREFIX_MAP[$prefix] ?? null;
    }

    /**
     * Detect wallet brand, falling back when the prefix is unknown.
     */
    public static function detectOr(string $fallback, ?string $phone): string
    {
        return self::detect($phone) ?? $fallback;
    }

    /**
     * @return list<string>
     */
    public static function knownNetworks(): array
    {
        return [
            self::MPESA,
            self::AIRTEL_MONEY,
            self::HALOPESA,
            self::MIXX_BY_YAS,
            self::TTCL,
        ];
    }

    /**
     * Normalize to local 0XXXXXXXXX (10 digits) or null.
     */
    public static function normalizeNational(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '255') && strlen($digits) >= 12) {
            $digits = '0'.substr($digits, 3);
        }

        if (strlen($digits) === 9 && in_array($digits[0], ['6', '7'], true)) {
            $digits = '0'.$digits;
        }

        if (strlen($digits) !== 10 || ! str_starts_with($digits, '0')) {
            return null;
        }

        return $digits;
    }
}
