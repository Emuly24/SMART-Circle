<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\View;

/**
 * Small inline icons that look the same on every phone (text symbols such as ◐ are
 * missing from some fonts). Static, trusted markup.
 */
final class Icons
{
    public static function feedback(string $status): string
    {
        $paths = match ($status) {
            'got_it' => '<path d="M5 12.5l4.2 4.2L19 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>',
            'partly' => '<circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M12 4a8 8 0 0 1 0 16z" fill="currentColor"/>',
            default => '<path d="M19 12a7 7 0 1 1-2.05-4.95" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><path d="M17.5 3.5v4h-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
        };

        return '<svg class="fb__icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">' . $paths . '</svg>';
    }
}
