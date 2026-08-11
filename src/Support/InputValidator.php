<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * Strict server-side input validation and sanitization via filter_var.
 */
final class InputValidator
{
    public static function string(mixed $value, int $minLength = 1, int $maxLength = 255): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $clean = trim(filter_var((string) $value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW));

        if ($clean === '' || mb_strlen($clean) < $minLength || mb_strlen($clean) > $maxLength) {
            return null;
        }

        return $clean;
    }

    public static function optionalEmail(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $clean = filter_var(trim((string) $value), FILTER_SANITIZE_EMAIL);

        if ($clean === false || $clean === '' || !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $clean;
    }

    public static function username(mixed $value): ?string
    {
        $clean = self::string($value, 3, 20);

        if ($clean === null || !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $clean)) {
            return null;
        }

        return $clean;
    }

    public static function phone(mixed $value): ?string
    {
        $clean = self::string($value, 7, 20);

        if ($clean === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $clean);

        if ($digits === null || strlen($digits) < 9 || strlen($digits) > 15) {
            return null;
        }

        return $clean;
    }

    public static function password(mixed $value, int $minLength = 5): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if (strlen($value) < $minLength) {
            return null;
        }

        return $value;
    }

    public static function login(mixed $value): ?string
    {
        $clean = trim((string) $value);

        if ($clean === '') {
            return null;
        }

        if (filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            return filter_var($clean, FILTER_SANITIZE_EMAIL) ?: null;
        }

        return self::phone($clean);
    }

    public static function enum(mixed $value, array $allowed): ?string
    {
        $clean = self::string($value, 1, 255);

        if ($clean === null || !in_array($clean, $allowed, true)) {
            return null;
        }

        return $clean;
    }

    public static function integer(mixed $value, int $min, int $max): ?int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => $min,
                'max_range' => $max,
            ],
        ]);

        return $filtered === false ? null : $filtered;
    }

    public static function date(mixed $value): ?string
    {
        $clean = self::string($value, 10, 10);

        if ($clean === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $clean);

        if ($date === false || $date->format('Y-m-d') !== $clean) {
            return null;
        }

        return $clean;
    }

    /** @param list<string> $allowed */
    public static function stringArray(mixed $value, array $allowed): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            $clean = self::string($item, 1, 100);

            if ($clean !== null && in_array($clean, $allowed, true)) {
                $result[] = $clean;
            }
        }

        return array_values(array_unique($result));
    }
}
