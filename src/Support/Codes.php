<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * Human-friendly random codes (no 0/O, 1/I/L confusion) for references,
 * account setup codes and document verification.
 */
final class Codes
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public static function random(int $length): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }

    /** Application reference, e.g. SC-27-7KQ4M. */
    public static function applicationReference(?int $year = null): string
    {
        return sprintf('SC-%02d-%s', ($year ?? (int) date('Y')) % 100, self::random(5));
    }

    /** Normalise what a person typed: upper-case, no spaces or dashes. */
    public static function normalize(string $typed): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $typed));
    }
}
