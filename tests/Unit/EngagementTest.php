<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Guardians\GuardianMessages;
use SmartCircle\Application\Students\DigestService;
use SmartCircle\Application\Students\SeatCheckService;
use SmartCircle\Application\Templates\TemplateService;

final class EngagementTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private static function seat(?string $seen, string $admitted, ?string $check = null, ?string $until = null): array
    {
        return ['last_seen_at' => $seen ?? '1970-01-01 00:00:00', 'admitted_at' => $admitted, 'last_check' => $check, 'paused_until' => $until];
    }

    public function testQuietStates(): void
    {
        $day = static fn (int $d): string => date('Y-m-d H:i:s', self::NOW - $d * 86400);
        self::assertSame('ok', SeatCheckService::describe(self::seat($day(3), $day(60)), self::NOW)['state']);
        self::assertSame('nudge', SeatCheckService::describe(self::seat($day(15), $day(60)), self::NOW)['state']);
        self::assertSame('review', SeatCheckService::describe(self::seat($day(30), $day(60)), self::NOW)['state']);
        $never = SeatCheckService::describe(self::seat(null, $day(25)), self::NOW);
        self::assertSame(['review', true, 25], [$never['state'], $never['never'], $never['days']]);
        self::assertSame('ok', SeatCheckService::describe(self::seat(null, $day(5)), self::NOW)['state'], 'newly admitted students are not chased');
        self::assertSame('paused', SeatCheckService::describe(self::seat($day(40), $day(90), 'pause', date('Y-m-d', self::NOW + 5 * 86400)), self::NOW)['state']);
        self::assertSame('review', SeatCheckService::describe(self::seat($day(40), $day(90), 'pause', date('Y-m-d', self::NOW - 2 * 86400)), self::NOW)['state'], 'a pause ends');
    }

    public function testDigestText(): void
    {
        $t = DigestService::studentText(['first' => 'Chikondi', 'new' => [['kind' => 'lesson', 'title' => 'Forces'], ['kind' => 'notes', 'title' => 'Atoms']],
            'due' => [['title' => 'Test 1', 'closes_at' => '2026-12-03 17:00:00']], 'marked' => 1, 'active' => 0]);
        self::assertStringContainsString('1 new lesson and 1 new set of notes for you: Forces, Atoms.', $t);
        self::assertStringContainsString('1 piece of work due this week (first: Test 1, Thu 3 Dec).', $t);
        self::assertStringContainsString('Feedback is ready on 1 piece', $t);
        self::assertStringContainsString('Nothing new this week', DigestService::studentText(['first' => 'A', 'new' => [], 'due' => [], 'marked' => 0, 'active' => 2]));
        self::assertSame('All clear: nothing is waiting for you this week.', DigestService::tutorText(['to_mark' => 0], 0));
        self::assertStringContainsString('3 piece(s) of work to mark; 2 quiet student(s)', DigestService::tutorText(['to_mark' => 3], 2));
    }

    public function testTemplatesFillAndValidate(): void
    {
        self::assertSame('Hi Ann, see x', TemplateService::fill('Hi {{ name }}, see {{link}}{{unknown}}', ['name' => 'Ann', 'link' => 'x']));
        $d = TemplateService::DEFAULTS['guardian_admission_en'];
        self::assertSame([], TemplateService::validate($d, 'Hi {{child_first}}', 'Please confirm: {{link}}'));
        self::assertArrayHasKey('body', TemplateService::validate($d, 'Hi', 'No link here'));
        self::assertStringContainsString('{{nope}}', TemplateService::validate($d, 'Hi {{nope}}', '{{link}}')['subject']);
        self::assertArrayHasKey('subject', TemplateService::validate($d, '', '{{link}}'));
    }

    public function testCustomGuardianWordingIsUsed(): void
    {
        $d = ['guardian' => 'Mayi', 'child' => 'Ann Banda', 'child_first' => 'Ann', 'form' => '2', 'school' => '', 'link' => 'L', 'tutor' => 'T', 'tutor_phone' => ''];
        self::assertSame('Moni Mayi! Ann: L', GuardianMessages::admission('ny', $d, 'Moni {{guardian}}! {{child_first}}: {{link}}'));
        self::assertStringStartsWith('Hello Mayi, this is T from SMART Circle', GuardianMessages::admission('en', $d));
    }
}
