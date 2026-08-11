<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * CSRF token generation and validation for form submissions.
 */
final class Csrf
{
    public const FIELD_NAME = 'csrf_token';

    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (
            !isset($_SESSION[self::SESSION_KEY])
            || !is_string($_SESSION[self::SESSION_KEY])
            || $_SESSION[self::SESSION_KEY] === ''
        ) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function regenerate(): string
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));

        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $submitted): bool
    {
        if ($submitted === null || $submitted === '') {
            return false;
        }

        $stored = $_SESSION[self::SESSION_KEY] ?? '';

        if (!is_string($stored) || $stored === '') {
            return false;
        }

        if (!defined('CSRF_SECRET')) {
            return hash_equals($stored, $submitted);
        }

        $expected = hash_hmac('sha256', $stored, CSRF_SECRET);

        return hash_equals($expected, $submitted);
    }

    public static function signedToken(): string
    {
        $raw = self::token();

        if (!defined('CSRF_SECRET')) {
            return $raw;
        }

        return hash_hmac('sha256', $raw, CSRF_SECRET);
    }

    public static function hiddenField(): string
    {
        $name = htmlspecialchars(self::FIELD_NAME, ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars(self::signedToken(), ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            $name,
            $value
        );
    }
}
