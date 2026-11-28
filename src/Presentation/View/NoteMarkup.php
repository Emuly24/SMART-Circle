<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\View;

use SmartCircle\Support\ReferenceTables;
use SmartCircle\Support\Symbols;

/**
 * The simple markup the tutor writes notes in, turned into safe HTML.
 * Everything is escaped FIRST; only the patterns below become tags.
 *
 *   # Heading            ## Smaller heading
 *   - item  /  * item    1. numbered item
 *   > Real life: ...     a highlighted box; consecutive "> " lines join into one box.
 *                        A leading label sets its colour: "Real life:", "Where it is used:",
 *                        "Why it matters:", "Did you know?" (amber); "Remember:", "Key idea:"
 *                        (blue); "Tip:", "Example:" (green); "Careful:" (warning)
 *   **bold**   *italic*
 *   H_2O  CO_{2}  x^2  10^{-3}  (subscript / superscript)
 *   ->  →     <-  ←     <=>  ⇌  (reaction arrows)
 *   <=  ≤   >=  ≥   !=  ≠   +-  ±   sqrt  √
 *   = v = u + at        a line starting with "= " is an equation or calculation step:
 *                       it is centred, and consecutive steps line up together
 *   frac{a}{b}          a stacked fraction
 *   \alpha \Delta \deg \therefore ...   symbols (see Support\Symbols)
 *   | a | b | c |       a table: one row per line; the first row is the heading row
 *                       (a row like |---|---| under it is optional)
 *   [table: bondEnthalpy]   a standard reference table (see Support\ReferenceTables)
 * A blank line starts a new paragraph. Paragraphs stay left-aligned (easier to read).
 */
final class NoteMarkup
{
    public static function render(string $text): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $text));
        $html = [];
        $para = [];
        $list = null;   // 'ul' | 'ol'
        $items = [];
        $calc = [];     // consecutive "= " lines
        $box = [];      // consecutive "> " lines
        $rows = [];     // consecutive "| ... |" lines

        $flushTable = static function () use (&$rows, &$html): void {
            if ($rows !== []) {
                $html[] = self::table($rows);
                $rows = [];
            }
        };

        $flushBox = static function () use (&$box, &$html): void {
            if ($box !== []) {
                $html[] = self::callout($box);
                $box = [];
            }
        };

        $flushCalc = static function () use (&$calc, &$html): void {
            if ($calc !== []) {
                $html[] = '<div class="note-calc">' . implode('', array_map(
                    static fn (string $l): string => '<p class="note-eq">' . self::math($l) . '</p>',
                    $calc
                )) . '</div>';
                $calc = [];
            }
        };

        $flushPara = static function () use (&$para, &$html): void {
            if ($para !== []) {
                $html[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $para)) . '</p>';
                $para = [];
            }
        };
        $flushList = static function () use (&$list, &$items, &$html): void {
            if ($list !== null) {
                $html[] = "<$list>" . implode('', array_map(static fn (string $i): string => '<li>' . self::inline($i) . '</li>', $items)) . "</$list>";
                [$list, $items] = [null, []];
            }
        };

        foreach ($lines as $line) {
            $t = rtrim($line);
            if (preg_match('/^\s*\|(.*)\|?\s*$/', $t, $m) && str_contains(trim($t), '|')) {
                $flushBox();
                $flushCalc();
                $flushPara();
                $flushList();
                $rows[] = $t;
                continue;
            }
            $flushTable();
            if (preg_match('/^\s*\[table:\s*([A-Za-z0-9]+)\s*\]\s*$/', $t, $m)) {
                $flushBox();
                $flushCalc();
                $flushPara();
                $flushList();
                $ref = ReferenceTables::get($m[1]);
                $html[] = $ref === null ? '<p class="muted">' . self::inline($t) . ' (no such table)</p>' : self::referenceTable($ref);
                continue;
            }
            if (preg_match('/^>\s?(.*)$/', $t, $m)) {
                $flushCalc();
                $flushPara();
                $flushList();
                $box[] = $m[1];
                continue;
            }
            $flushBox();
            if (preg_match('/^=\s+(.+)$/', $t, $m)) {
                $flushPara();
                $flushList();
                $calc[] = $m[1];
                continue;
            }
            $flushCalc();
            if (trim($t) === '') {
                $flushPara();
                $flushList();
                continue;
            }
            if (preg_match('/^(#{1,2})\s+(.+)$/', $t, $m)) {
                $flushPara();
                $flushList();
                $tag = strlen($m[1]) === 1 ? 'h2' : 'h3';
                $html[] = "<$tag>" . self::inline($m[2]) . "</$tag>";
            } elseif (preg_match('/^\s*[-*]\s+(.+)$/', $t, $m)) {
                $flushPara();
                if ($list !== 'ul') {
                    $flushList();
                    $list = 'ul';
                }
                $items[] = $m[1];
            } elseif (preg_match('/^\s*\d+[.)]\s+(.+)$/', $t, $m)) {
                $flushPara();
                if ($list !== 'ol') {
                    $flushList();
                    $list = 'ol';
                }
                $items[] = $m[1];
            } else {
                $flushList();
                $para[] = $t;
            }
        }
        $flushTable();
        $flushBox();
        $flushCalc();
        $flushPara();
        $flushList();

        return implode("\n", $html);
    }

    /** Splits "| a | b |" into its cells. @return list<string> */
    public static function cells(string $row): array
    {
        $row = trim($row);
        $row = (string) preg_replace('/^\||\|$/', '', $row);

        return array_map('trim', explode('|', $row));
    }

    /** True for a "|---|:---:|" separator row. */
    public static function isSeparator(string $row): bool
    {
        return (bool) preg_match('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/', $row);
    }

    /** @param list<string> $rows the table's lines */
    private static function table(array $rows): string
    {
        $data = [];
        foreach ($rows as $r) {
            if (!self::isSeparator($r)) {
                $data[] = self::cells($r);
            }
        }
        if ($data === []) {
            return '';
        }
        $head = array_shift($data);

        return self::tableHtml($head, $data, '', '');
    }

    /** @param array{title: string, cols: list<string>, rows: list<list<string>>, note?: string} $t */
    private static function referenceTable(array $t): string
    {
        return self::tableHtml($t['cols'], $t['rows'], $t['title'], $t['note'] ?? '');
    }

    /** @param list<string> $head @param list<list<string>> $rows */
    private static function tableHtml(array $head, array $rows, string $title, string $note): string
    {
        $width = max(count($head), ...array_map('count', $rows ?: [[]]));
        $cell = static fn (string $tag, string $v): string => "<$tag>" . self::inline($v) . "</$tag>";
        $line = static function (array $r, string $tag) use ($width, $cell): string {
            $r = array_pad($r, $width, '');
            return '<tr>' . implode('', array_map(static fn (string $v): string => $cell($tag, $v), $r)) . '</tr>';
        };
        $out = '<div class="note-table-wrap"><table class="note-table">';
        if ($title !== '') {
            $out .= '<caption>' . self::inline($title) . '</caption>';
        }
        $out .= '<thead>' . $line($head, 'th') . '</thead><tbody>' . implode('', array_map(static fn (array $r): string => $line($r, 'td'), $rows)) . '</tbody></table>';
        if ($note !== '') {
            $out .= '<p class="note-table__note">' . self::inline($note) . '</p>';
        }

        return $out . '</div>';
    }

    /** Labels that start a box, and the colour each gives it. */
    private const LABELS = [
        'real life' => 'life', 'in real life' => 'life', 'where it is used' => 'life', "where it's used" => 'life',
        'why it matters' => 'life', 'did you know' => 'life', 'remember' => 'key', 'key idea' => 'key',
        'key point' => 'key', 'note' => 'key', 'tip' => 'tip', 'example' => 'tip', 'careful' => 'warn', 'warning' => 'warn',
    ];

    /** @param list<string> $lines the box's lines (without "> ") */
    private static function callout(array $lines): string
    {
        $variant = 'life';
        $label = '';
        if (preg_match('/^\s*([A-Za-z\' ]{2,20}?)\s*([:?])\s*(.*)$/u', $lines[0], $m) && isset(self::LABELS[strtolower(trim($m[1]))])) {
            $variant = self::LABELS[strtolower(trim($m[1]))];
            $label = '<span class="note-callout__label">' . self::inline(trim($m[1]) . $m[2]) . '</span> ';
            $lines[0] = $m[3];
        }
        $body = implode('<br>', array_map([self::class, 'inline'], $lines));
        $body = (string) preg_replace('/^(<br>)+/', '', $body);

        return '<aside class="note-callout note-callout--' . $variant . '">' . $label . $body . '</aside>';
    }

    /** An equation line: inline patterns, plus * as a times sign (no bold/italic there). */
    public static function math(string $text): string
    {
        return self::inline(str_replace('*', '×', $text));
    }

    /** Inline patterns on ESCAPED text. */
    public static function inline(string $text): string
    {
        $s = htmlspecialchars(Symbols::replace($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = str_replace(['&lt;=&gt;', '-&gt;', '&lt;-', '&lt;=', '&gt;=', '!=', '+-', 'sqrt'], ['⇌', '→', '←', '≤', '≥', '≠', '±', '√'], $s);
        $s = (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
        $s = (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $s);
        $s = (string) preg_replace('/\^\{([^}]{1,20})\}/u', '<sup>$1</sup>', $s);
        $s = (string) preg_replace('/\^(-?[0-9a-zA-Z+]{1,6})/u', '<sup>$1</sup>', $s);
        $s = (string) preg_replace('/_\{([^}]{1,20})\}/u', '<sub>$1</sub>', $s);
        $s = (string) preg_replace('/(?<=[A-Za-z)\]])_([0-9]{1,3})/u', '<sub>$1</sub>', $s);
        $s = (string) preg_replace('/frac\{([^{}]{1,60})\}\{([^{}]{1,60})\}/u', '<span class="frac"><span class="frac__n">$1</span><span class="frac__d">$2</span></span>', $s);

        return $s;
    }
}
