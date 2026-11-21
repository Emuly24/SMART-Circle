<?php

declare(strict_types=1);

namespace SmartCircle\Application\Topics;

/**
 * Turns one line of a syllabus list into a topic:
 *
 *   "Unit 6: Gas Laws (Boyle's Law, Charles' Law, Pressure Law)"
 *     -> unit "Unit 6", title "Gas Laws", subtopics ["Boyle's Law", "Charles' Law", "Pressure Law"]
 *   "Topic 9: Alkanoates (Esters) (Fats & oils, Saponification, Uses)"
 *     -> unit "Topic 9", title "Alkanoates (Esters)", subtopics ["Fats & oils", "Saponification", "Uses"]
 *   "Circle Geometry (Angle Properties)"  (one item, no comma: part of the title)
 *     -> unit null, title "Circle Geometry (Angle Properties)", subtopics []
 *
 * Used for the starting catalogue and for the tutor's "paste a list" import.
 */
final class TopicLineParser
{
    /** @return array{unit: ?string, title: string, subtopics: list<string>}|null null for blank/comment lines */
    public static function parse(string $line): ?array
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $line));
        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }
        $line = ltrim($line, "-•* \t");

        $unit = null;
        if (preg_match('/^((?:Preliminary\s+)?(?:Unit|Topic|Chapter)\s*[0-9IVX]*)\s*[:.\-–]\s*(.+)$/iu', $line, $m)) {
            $unit = trim($m[1]);
            $line = trim($m[2]);
        }

        $subtopics = [];
        // A final bracket group containing commas lists the subtopics.
        if (str_ends_with($line, ')')) {
            $depth = 0;
            for ($i = strlen($line) - 1; $i >= 0; $i--) {
                if ($line[$i] === ')') {
                    $depth++;
                } elseif ($line[$i] === '(') {
                    $depth--;
                    if ($depth === 0) {
                        $inside = substr($line, $i + 1, -1);
                        if (str_contains($inside, ',') && !preg_match('/\be\.g\./i', $inside)) {
                            $subtopics = array_values(array_filter(array_map(
                                static fn (string $s): string => trim($s),
                                self::splitTopLevel($inside)
                            ), static fn (string $s): bool => $s !== ''));
                            $line = rtrim(substr($line, 0, $i));
                        }
                        break;
                    }
                }
            }
        }

        $title = trim($line, " \t:;");
        if ($title === '') {
            return null;
        }

        return ['unit' => $unit, 'title' => mb_substr($title, 0, 200), 'subtopics' => array_map(static fn (string $s): string => mb_substr($s, 0, 200), $subtopics)];
    }

    /**
     * Parse a whole pasted list; returns only the topics.
     * @return list<array{unit: ?string, title: string, subtopics: list<string>}>
     */
    public static function parseList(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $topic = self::parse($line);
            if ($topic !== null) {
                $out[] = $topic;
            }
        }

        return $out;
    }

    /** Split on commas that are not inside nested brackets. @return list<string> */
    private static function splitTopLevel(string $s): array
    {
        $parts = [];
        $depth = 0;
        $current = '';
        foreach (str_split($s) as $ch) {
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            }
            if ($ch === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $ch;
        }
        $parts[] = $current;

        return $parts;
    }
}
