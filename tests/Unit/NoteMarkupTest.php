<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Presentation\View\NoteMarkup;

final class NoteMarkupTest extends TestCase
{
    public function testBlocks(): void
    {
        $html = NoteMarkup::render("# Density\nDensity is mass per volume.\nIt is measured in kg/m^3.\n\n- Water: 1000 kg/m^3\n- Iron: 7874 kg/m^3\n\n1. Weigh\n2. Measure\n\n> Real life: a boat floats because it is less dense than water.\n## Practice");
        self::assertStringContainsString('<h2>Density</h2>', $html);
        self::assertStringContainsString('<p>Density is mass per volume.<br>It is measured in kg/m<sup>3</sup>.</p>', $html);
        self::assertStringContainsString('<ul><li>Water: 1000 kg/m<sup>3</sup></li><li>Iron: 7874 kg/m<sup>3</sup></li></ul>', $html);
        self::assertStringContainsString('<ol><li>Weigh</li><li>Measure</li></ol>', $html);
        self::assertStringContainsString('<aside class="note-callout note-callout--life"><span class="note-callout__label">Real life:</span> a boat floats', $html);
        self::assertStringContainsString('<h3>Practice</h3>', $html);
    }

    public function testScienceNotation(): void
    {
        self::assertSame('H<sub>2</sub>O and CO<sub>2</sub>', NoteMarkup::inline('H_2O and CO_{2}'));
        self::assertSame('x<sup>2</sup> + 10<sup>-3</sup> + 2<sup>n+1</sup>', NoteMarkup::inline('x^2 + 10^{-3} + 2^{n+1}'));
        self::assertSame('N<sub>2</sub> + 3H<sub>2</sub> ⇌ 2NH<sub>3</sub>', NoteMarkup::inline('N_2 + 3H_2 <=> 2NH_3'));
        self::assertSame('Zn + 2HCl → ZnCl<sub>2</sub> + H<sub>2</sub>', NoteMarkup::inline('Zn + 2HCl -> ZnCl_2 + H_2'));
        self::assertSame('<strong>Mass</strong> is not <em>weight</em>', NoteMarkup::inline('**Mass** is not *weight*'));
        self::assertSame('snake_case_name stays', NoteMarkup::inline('snake_case_name stays'), 'underscores between letters are not subscripts');
        self::assertSame('2 * 3 * 4', NoteMarkup::inline('2 * 3 * 4'), 'multiplication signs are not italics');
    }

    public function testEverythingIsEscaped(): void
    {
        $html = NoteMarkup::render("<script>alert(1)</script>\n**<img src=x onerror=alert(1)>**");
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testEquationLinesAreCentredAndGrouped(): void
    {
        $html = NoteMarkup::render("Find v:\n= v = u + at\n= v = 0 + 9.8 * 2\n= v = 19.6 m/s\nDone.");
        self::assertStringContainsString('<div class="note-calc"><p class="note-eq">v = u + at</p><p class="note-eq">v = 0 + 9.8 × 2</p><p class="note-eq">v = 19.6 m/s</p></div>', $html);
        self::assertStringContainsString('<p>Find v:</p>', $html);
        self::assertStringContainsString('<p>Done.</p>', $html);
        // Two groups when separated by text.
        self::assertSame(2, substr_count(NoteMarkup::render("= a = 1\ntext\n= b = 2"), 'note-calc'));
    }

    public function testMathSymbolsAndSafety(): void
    {
        self::assertStringContainsString('x ≥ 0, y ≤ 3, a ≠ b, ±2, √9', NoteMarkup::inline('x >= 0, y <= 3, a != b, +-2, sqrt9'));
        self::assertStringContainsString('<sup>2</sup>', NoteMarkup::render('= x^2 + 1 = 5'));
        $evil = NoteMarkup::render('= <script>alert(1)</script> * 2');
        self::assertStringNotContainsString('<script>', $evil);
        self::assertStringContainsString('&lt;script&gt;', $evil);
        // Outside equation lines, * still means italics.
        self::assertStringContainsString('<em>very</em>', NoteMarkup::render('It is *very* important'));
    }
}
