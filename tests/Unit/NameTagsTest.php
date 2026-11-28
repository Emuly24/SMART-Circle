<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Support\NameTags;

final class NameTagsTest extends TestCase
{
    public function testValues(): void
    {
        $v = NameTags::values(['full_name' => '  Chikondi  Grace Banda ', 'title' => 'Mrs']);
        self::assertSame('Chikondi', $v['first_name']);
        self::assertSame('Banda', $v['surname']);
        self::assertSame('Chikondi Grace Banda', $v['full_name']);
        self::assertSame('Mrs Banda', $v['title_surname']);
        self::assertSame('Mrs Chikondi Grace Banda', $v['title_full_name']);
        // No (or an unknown) title: the full name is used.
        self::assertSame('Chikondi Grace Banda', NameTags::values(['full_name' => 'Chikondi Grace Banda', 'title' => 'Sir'])['title_surname']);
        self::assertSame('Tamanda', NameTags::values(['full_name' => 'Tamanda'])['surname']);
        self::assertSame('Thoko', NameTags::values(['full_name' => 'Mayi Phiri', 'student_name' => 'Thoko Phiri'])['student_first_name']);
    }

    public function testFillAndMarks(): void
    {
        $p = ['full_name' => 'Kondwani Phiri', 'title' => 'Mr'];
        self::assertSame('Dear Mr Phiri, hello Kondwani.', NameTags::fill('Dear {title_surname}, hello {first_name}.', $p));
        self::assertSame('Hi Kondwani', NameTags::fill('Hi {name}', $p), 'the older {name} still works');
        self::assertSame('Hi there', NameTags::fill('Hi {student_first_name}', $p), 'a missing value reads naturally');
        self::assertSame('{unknown} stays', NameTags::fill('{unknown} stays', $p));
        $marked = NameTags::fill('Dear {surname}', $p, true);
        self::assertSame("Dear \u{E000}Phiri\u{E001}", $marked);
        self::assertSame('Dear Phiri', NameTags::unmark($marked));
    }

    public function testHtmlAndReader(): void
    {
        $html = NameTags::html(e('Hello {first_name} <b>'), ['full_name' => '<Thoko> Banda']);
        self::assertSame('Hello <span class="name-tag">&lt;Thoko&gt;</span> &lt;b&gt;', $html, 'names are escaped');
        self::assertStringContainsString('name-tag--slot', NameTags::html('Hi {first_name}', null));
        $members = [['user_id' => 1, 'full_name' => 'Tutor One'], ['user_id' => 2, 'full_name' => 'Student Two'], ['user_id' => 3, 'full_name' => 'Student Three']];
        self::assertSame(3, NameTags::readerFor(1, 3, $members)['user_id'], 'a recipient sees their own name');
        self::assertNull(NameTags::readerFor(1, 1, $members), 'the sender of a message to many sees slots');
        self::assertSame(2, NameTags::readerFor(1, 1, array_slice($members, 0, 2))['user_id'], 'in a one-to-one chat the sender sees the other name');
        self::assertSame('Hi [First name]', NameTags::preview('Hi {first_name}', null));
    }
}
