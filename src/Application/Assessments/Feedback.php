<?php

declare(strict_types=1);

namespace SmartCircle\Application\Assessments;

/**
 * Encouraging marking: no red crosses. Each answer is "Got it", "Partly there" or "Not yet",
 * with what went well, where it went off track, and the next step.
 */
final class Feedback
{
    /** @var array<string, array{0: string, 1: string}> status => [symbol, label] */
    public const STATUSES = [
        'got_it' => ['✓', 'Got it'],
        'partly' => ['◐', 'Partly there'],
        'not_yet' => ['↻', 'Not yet'],
    ];

    public static function status(float $score, float $max): string
    {
        if ($max > 0 && $score >= $max - 0.001) {
            return 'got_it';
        }

        return $score > 0 ? 'partly' : 'not_yet';
    }

    /** One line that acknowledges the work, whatever the score. */
    public static function headline(float $score, float $max): string
    {
        $pct = $max > 0 ? $score / $max : 0;

        return match (true) {
            $pct >= .9 => 'Excellent work. You have really understood this.',
            $pct >= .7 => 'Strong work. A few steps to polish and you have it.',
            $pct >= .5 => 'Good progress. You are more than halfway there.',
            $pct > 0 => 'You have made a start. The notes below show your next steps.',
            default => 'Thank you for trying. Every answer below has a next step to help you get there.',
        };
    }
}
