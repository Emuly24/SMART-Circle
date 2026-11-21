<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * Malawian phone numbers, stored in one canonical form: +265XXXXXXXXX.
 *
 * Accepts the ways people actually type them:
 *   0999 123 456, 999123456, +265 999 123 456, 265999123456, 00265 999...
 */
final class PhoneNumber
{
    private const COUNTRY_CODE = '265';

    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (str_starts_with($digits, '00' . self::COUNTRY_CODE)) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, self::COUNTRY_CODE) && strlen($digits) === 12) {
            $digits = substr($digits, 3);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = substr($digits, 1);
        }

        // Malawian subscriber numbers have 9 digits and do not start with 0.
        if (!preg_match('/^[1-9]\d{8}$/', $digits)) {
            return null;
        }

        return '+' . self::COUNTRY_CODE . $digits;
    }

    /** +265999123456 -> 0999 123 456 (how people in Malawi read numbers). */
    public static function display(string $canonical): string
    {
        if (!preg_match('/^\+265(\d{3})(\d{3})(\d{3})$/', $canonical, $m)) {
            return $canonical;
        }

        return "0{$m[1]} {$m[2]} {$m[3]}";
    }
}
