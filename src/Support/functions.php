<?php

declare(strict_types=1);

/**
 * Global helpers available to templates.
 */

if (!function_exists('e')) {
    /** Escape any value for safe output in HTML text and attributes. */
    function e(string|int|float|bool|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
