<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Library\LibraryService;
use SmartCircle\Presentation\View\NoteMarkup;

final class LibraryTest extends TestCase
{
    public function testFiltersAcceptOnlyRealFormsAndSubjects(): void
    {
        self::assertSame(['form' => 3, 'subject' => 'physics'], LibraryService::filters('3', 'physics'));
        self::assertSame(['form' => null, 'subject' => null], LibraryService::filters('9', 'history'));
        self::assertSame(['form' => null, 'subject' => null], LibraryService::filters('', ''));
        self::assertSame(['form' => null, 'subject' => null], LibraryService::filters("1' OR 1=1", '<script>'));
    }

    public function testRealLifeBoxGetsALabelAndColour(): void
    {
        $html = NoteMarkup::render("> Real life: a canoe **floats**.\n> It is less dense than water.");
        self::assertSame('<aside class="note-callout note-callout--life"><span class="note-callout__label">Real life:</span> a canoe <strong>floats</strong>.<br>It is less dense than water.</aside>', $html);
    }

    public function testBoxLabelsSetTheirColour(): void
    {
        self::assertStringContainsString('note-callout--key', NoteMarkup::render('> Remember: x^2'));
        self::assertStringContainsString('note-callout--tip', NoteMarkup::render('> Example: 2 + 2'));
        self::assertStringContainsString('note-callout--warn', NoteMarkup::render('> Careful: hot'));
        self::assertStringContainsString('note-callout--life', NoteMarkup::render('> Did you know? Bees'));
    }

    public function testUnknownLabelIsPlainTextAndEscaped(): void
    {
        self::assertSame('<aside class="note-callout note-callout--life">Ratio: 2:3</aside>', NoteMarkup::render('> Ratio: 2:3'));
        self::assertStringNotContainsString('<b>', NoteMarkup::render('> Real life: <b>x</b>'));
    }

    public function testSeparateBoxesStaySeparate(): void
    {
        self::assertSame(2, substr_count(NoteMarkup::render("> Tip: one\n\n> Tip: two"), '<aside'));
    }
}
