<?php

declare(strict_types=1);

namespace SmartCircle\Application\Assessments;

/**
 * Automatic marking. Pure functions: a question's type and data plus the student's answer
 * give a score, or null when the tutor must mark it (short written answers).
 */
final class Marker
{
    public const TYPES = [
        'mcq' => 'Multiple choice',
        'numeric' => 'Number (with units)',
        'grammar_fill' => 'Fill in the gap (grammar)',
        'short_text' => 'Short written answer (you mark it)',
    ];

    /** @param array<string, mixed> $data */
    public static function score(string $type, array $data, string $answer, float $max): ?float
    {
        $answer = trim($answer);
        if ($type === 'short_text') {
            return null;
        }
        if ($answer === '') {
            return 0.0;
        }

        return match ($type) {
            'mcq' => (string) ($data['correct'] ?? '') === $answer ? $max : 0.0,
            'grammar_fill' => self::grammar($data, $answer) ? $max : 0.0,
            'numeric' => self::numeric($data, $answer, $max),
            default => null,
        };
    }

    /** @param array<string, mixed> $data */
    private static function grammar(array $data, string $answer): bool
    {
        $mine = self::normaliseText($answer);
        foreach ((array) ($data['accepted'] ?? []) as $accepted) {
            if (self::normaliseText((string) $accepted) === $mine) {
                return true;
            }
        }

        return false;
    }

    /** Case, spaces and end punctuation do not matter; curly apostrophes count as straight ones. */
    public static function normaliseText(string $s): string
    {
        $s = str_replace(["\u{2019}", "\u{2018}"], "'", mb_strtolower(trim($s)));
        $s = (string) preg_replace('/\s+/u', ' ', $s);

        return rtrim($s, " .!?,;:");
    }

    /**
     * Full marks when the value is within the tolerance. When a unit is required, a missing or
     * wrong unit gives half marks: the working was right but the answer is incomplete.
     * @param array<string, mixed> $data answer, tolerance_pct, unit, unit_required
     */
    private static function numeric(array $data, string $answer, float $max): float
    {
        $parsed = self::parseNumber($answer);
        if ($parsed === null || !is_numeric($data['answer'] ?? null)) {
            return 0.0;
        }
        [$value, $unit] = $parsed;
        $expected = (float) $data['answer'];
        $tolerance = abs($expected) * max(0.0, (float) ($data['tolerance_pct'] ?? 1)) / 100;
        if (abs($value - $expected) > max($tolerance, 1e-9)) {
            return 0.0;
        }
        $wantUnit = self::normaliseUnit((string) ($data['unit'] ?? ''));
        if (!empty($data['unit_required']) && $wantUnit !== '' && self::normaliseUnit($unit) !== $wantUnit) {
            return round($max / 2, 2);
        }

        return $max;
    }

    /**
     * "2.5", "2,500", "-0.75 m/s", "3.2e4 J", "1/2" are understood.
     * @return array{0: float, 1: string}|null value and whatever unit follows it
     */
    public static function parseNumber(string $answer): ?array
    {
        $a = trim(str_replace(["\u{2212}", "\u{00D7}"], ['-', 'x'], $answer));
        if (preg_match('/^(-?\d+)\s*\/\s*(\d+)\s*(.*)$/u', $a, $m) && (int) $m[2] !== 0) {
            return [(int) $m[1] / (int) $m[2], trim($m[3])];
        }
        if (preg_match('/^(-?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?(?:\s*(?:e|x\s*10\^?)\s*-?\d+)?)\s*(.*)$/iu', $a, $m)) {
            $num = str_replace([',', ' '], '', $m[1]);
            $num = (string) preg_replace('/x10\^?/i', 'e', $num);

            return is_numeric($num) ? [(float) $num, trim($m[2])] : null;
        }

        return null;
    }

    public static function normaliseUnit(string $unit): string
    {
        $u = str_replace(['²', '³', '·', '−'], ['2', '3', '', '-'], trim($unit));
        $u = str_replace(['^', ' ', '*'], '', $u);

        return $u === 'ohm' || $u === 'ohms' || $u === 'Ω' ? 'Ω' : $u;
    }
}
