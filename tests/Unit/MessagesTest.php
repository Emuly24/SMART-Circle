<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Messages\MessageService;
use SmartCircle\Application\Messages\SupportService;
use SmartCircle\Application\Students\StudentOverviewService;

final class MessagesTest extends TestCase
{
    public function testBodyChecks(): void
    {
        self::assertNotNull(MessageService::checkBody('   '));
        self::assertNotNull(MessageService::checkBody(str_repeat('a', MessageService::MAX_LENGTH + 1)));
        self::assertNull(MessageService::checkBody('Hello tutor'));
    }

    public function testSeenOnlyAfterTheOtherPersonReadMyLatestMessage(): void
    {
        $messages = [
            ['user_id' => 1, 'created_at' => '2026-11-20 10:00:00'],
            ['user_id' => 2, 'created_at' => '2026-11-20 10:05:00'],
            ['user_id' => 1, 'created_at' => '2026-11-20 10:10:00'],
        ];
        $members = [
            ['user_id' => 1, 'full_name' => 'Tutor', 'last_read_at' => '2026-11-20 10:10:00'],
            ['user_id' => 2, 'full_name' => 'Chikondi', 'last_read_at' => '2026-11-20 10:06:00'],
        ];
        self::assertNull(MessageService::seenBy($messages, $members, 1));
        $members[1]['last_read_at'] = '2026-11-20 10:12:00';
        self::assertSame(['Chikondi'], MessageService::seenBy($messages, $members, 1)['names']);
        // Never shown for announcements (more than two members) or when I have not written.
        self::assertNull(MessageService::seenBy($messages, [...$members, ['user_id' => 3, 'full_name' => 'X', 'last_read_at' => null]], 1));
        self::assertNull(MessageService::seenBy($messages, $members, 5));
    }

    public function testGuardianTextNeverCarriesTheConversation(): void
    {
        $t = SupportService::guardianText('Mrs Banda', 'Chikondi Banda');
        self::assertStringStartsWith('Dear Mrs Banda', $t);
        self::assertStringContainsString('Chikondi Banda', $t);
        self::assertStringStartsWith('Hello', SupportService::guardianText('', 'Chikondi'));
    }

    /** @return array<string, mixed> */
    private static function student(array $over = []): array
    {
        return $over + ['full_name' => 'Chikondi Banda', 'phone' => '+265999123456', 'email' => null, 'guardian_name' => 'Mrs Banda',
            'guardian_phone' => '0888123456', 'last_login_at' => '2026-11-25 08:00:00', 'lessons_14d' => 2, 'work_30d' => 1, 'missed_30d' => 0, 'overall' => 62.0];
    }

    public function testFlags(): void
    {
        $now = (int) strtotime('2026-11-26 12:00:00');
        self::assertSame([], StudentOverviewService::flags(self::student(), false, $now));
        $keys = static fn (array $f): array => array_column($f, 'key');
        self::assertSame(['quiet'], $keys(StudentOverviewService::flags(self::student(['last_login_at' => '2026-11-10 08:00:00']), false, $now)));
        self::assertSame(['quiet'], $keys(StudentOverviewService::flags(self::student(['last_login_at' => null]), false, $now)));
        // School holidays: being away is normal.
        self::assertSame([], StudentOverviewService::flags(self::student(['last_login_at' => '2026-11-10 08:00:00']), true, $now));
        self::assertSame(['missed', 'low'], $keys(StudentOverviewService::flags(self::student(['missed_30d' => 2, 'overall' => 35.5]), false, $now)));
        self::assertSame(['idle'], $keys(StudentOverviewService::flags(self::student(['lessons_14d' => 0, 'work_30d' => 0]), false, $now)));
        self::assertSame('1 piece of work missed this month', StudentOverviewService::flags(self::student(['missed_30d' => 1]), false, $now)[0]['label']);
    }

    public function testContactLinks(): void
    {
        $l = StudentOverviewService::links(self::student(['email' => 'chikondi@example.com']));
        $hrefs = array_column($l['student'], 'href', 'label');
        self::assertSame('tel:+265999123456', $hrefs['Call']);
        self::assertStringStartsWith('https://wa.me/265999123456?text=Hello%20Chikondi', $hrefs['WhatsApp']);
        self::assertStringStartsWith('sms:+265999123456?body=', $hrefs['SMS']);
        self::assertStringStartsWith('mailto:chikondi@example.com', $hrefs['Email']);
        $g = array_column($l['guardian'], 'href', 'label');
        self::assertSame('tel:+265888123456', $g['Call']);
        self::assertStringContainsString('wa.me/265888123456', $g['WhatsApp']);
        self::assertSame([], StudentOverviewService::links(self::student(['guardian_phone' => '']))['guardian']);
        self::assertArrayNotHasKey('Email', array_column(StudentOverviewService::links(self::student(['email' => 'not-an-email']))['student'], 'href', 'label'));
    }
}
