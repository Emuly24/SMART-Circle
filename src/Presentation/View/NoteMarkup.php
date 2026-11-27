<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\View;

/**
 * The simple markup the tutor writes notes in, turned into safe HTML.
 * Everything is escaped FIRST; only the patterns below become tags.
 *
 *   # Heading            ## Smaller heading
 *   - item  /  * item    1. numbered item
 *   > Real life: ...     (a highlighted "real life" box)
 *   **bold**   *italic*
 *   H_2O  CO_{2}  x^2  10^{-3}  (subscript / superscript)
 *   ->  →     <-  ←     <=>  ⇌  (reaction arrows)
 *   <=  ≤   >=  ≥   !=  ≠   +-  ±   sqrt  √
 *   = v = u + at        a line starting with "= " is an equation or calculation step:
 *                       it is centred, and consecutive steps line up together
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
            } elseif (preg_match('/^>\s?(.*)$/', $t, $m)) {
                $flushPara();
                $flushList();
                $html[] = '<aside class="note-callout">' . self::inline($m[1]) . '</aside>';
            } else {
                $flushList();
                $para[] = $t;
            }
        }
        $flushCalc();
        $flushPara();
        $flushList();

        return implode("\n", $html);
    }

    /** An equation line: inline patterns, plus * as a times sign (no bold/italic there). */
    public static function math(string $text): string
    {
        return self::inline(str_replace('*', '×', $text));
    }

    /** Inline patterns on ESCAPED text. */
    public static function inline(string $text): string
    {
        $s = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = str_replace(['&lt;=&gt;', '-&gt;', '&lt;-', '&lt;=', '&gt;=', '!=', '+-', 'sqrt'], ['⇌', '→', '←', '≤', '≥', '≠', '±', '√'], $s);
        $s = (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
        $s = (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $s);
        $s = (string) preg_replace('/\^\{([^}]{1,20})\}/u', '<sup>$1</sup>', $s);
        $s = (string) preg_replace('/\^(-?[0-9a-zA-Z+]{1,6})/u', '<sup>$1</sup>', $s);
        $s = (string) preg_replace('/_\{([^}]{1,20})\}/u', '<sub>$1</sub>', $s);
        $s = (string) preg_replace('/(?<=[A-Za-z)\]])_([0-9]{1,3})/u', '<sub>$1</sub>', $s);

        return $s;
    }
}
