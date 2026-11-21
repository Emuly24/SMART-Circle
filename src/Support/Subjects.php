<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * The five subjects and the track rules.
 *   Forms 1-2: "general" track, all five subjects.
 *   Forms 3-4: "science" track if the student takes Physics AND Chemistry (all five),
 *              otherwise "humanities" (Mathematics, Biology, English grammar).
 */
final class Subjects
{
    public const NAMES = [
        'mathematics' => 'Mathematics',
        'physics' => 'Physics',
        'chemistry' => 'Chemistry',
        'biology' => 'Biology',
        'english' => 'English grammar',
    ];

    public const HUMANITIES = ['mathematics', 'biology', 'english'];

    public const TRACKS = [
        'general' => 'Forms 1–2 (all subjects)',
        'science' => 'Science (Physics and Chemistry)',
        'humanities' => 'Humanities',
    ];

    /** @param list<string> $subjectsTaken */
    public static function track(int $form, array $subjectsTaken): string
    {
        if ($form <= 2) {
            return 'general';
        }

        return in_array('physics', $subjectsTaken, true) && in_array('chemistry', $subjectsTaken, true)
            ? 'science'
            : 'humanities';
    }

    /** @return list<string> subject keys a track may study */
    public static function forTrack(string $track): array
    {
        return $track === 'humanities' ? self::HUMANITIES : array_keys(self::NAMES);
    }

    /**
     * @param mixed $input raw checkbox values
     * @return list<string> only known subject keys, in canonical order
     */
    public static function clean(mixed $input): array
    {
        $values = is_array($input) ? array_map('strval', $input) : [];

        return array_values(array_filter(array_keys(self::NAMES), static fn (string $s): bool => in_array($s, $values, true)));
    }

    /** @param list<string> $keys */
    public static function names(array $keys): string
    {
        return implode(', ', array_map(static fn (string $k): string => self::NAMES[$k] ?? $k, $keys));
    }
}
