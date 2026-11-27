<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Admissions\SeatChart;
use SmartCircle\Application\Messages\MessageService;
use SmartCircle\Application\Settings\SettingsService;
use SmartCircle\Application\Team\TeamAgreement;
use SmartCircle\Application\Team\TeamService;
use SmartCircle\Presentation\Http\Context;

final class TeamTest extends TestCase
{
    public function testInvitationValidation(): void
    {
        $ok = TeamService::validate(['full_name' => ' Mercy Mentor ', 'phone' => '0989 000 001', 'subjects' => ['physics', 'latin', 'biology']]);
        self::assertSame([], $ok['errors']);
        self::assertSame('+265989000001', $ok['clean']['phone']);
        self::assertSame(['physics', 'biology'], $ok['clean']['subjects']);
        $bad = TeamService::validate(['full_name' => 'X', 'phone' => '123', 'email' => 'nope', 'subjects' => []]);
        self::assertSame(['full_name', 'phone', 'email', 'subjects'], array_keys($bad['errors']));
    }

    public function testTeamTutorsOpenOnlyTheirSubjectsOrTheirOwnUntaggedWork(): void
    {
        self::assertTrue(TeamService::mayOpen(['subject' => 'physics', 'owner' => 1], ['physics'], 9));
        self::assertFalse(TeamService::mayOpen(['subject' => 'chemistry', 'owner' => 9], ['physics'], 9));
        self::assertTrue(TeamService::mayOpen(['subject' => null, 'owner' => 9], ['physics'], 9));
        self::assertFalse(TeamService::mayOpen(['subject' => null, 'owner' => 1], ['physics'], 9));
    }

    public function testContextScope(): void
    {
        $c = new Context();
        self::assertTrue($c->canTeach('chemistry'));
        $c->setSubjects(['physics']);
        self::assertTrue($c->canTeach('physics'));
        self::assertFalse($c->canTeach('chemistry'));
        self::assertFalse($c->canTeach(null));
    }

    public function testAnnouncementAudiences(): void
    {
        self::assertSame(['subject:physics' => 'Students taking Physics'], MessageService::audiencesFor(['physics']));
        $admin = MessageService::audiencesFor(null);
        self::assertArrayHasKey('students', $admin);
        self::assertArrayHasKey('subject:english', $admin);
    }

    public function testAgreementCoversTheEssentials(): void
    {
        $text = TeamAgreement::PHILOSOPHY . implode(' ', array_merge(...TeamAgreement::PRINCIPLES)) . implode(' ', TeamAgreement::DUTIES);
        foreach (['free', 'favours', 'Honesty', 'Got it', 'Safeguarding', 'Privacy', 'within 7 days'] as $needle) {
            self::assertStringContainsString($needle, $text);
        }
    }

    public function testSettings(): void
    {
        $ok = SettingsService::validate(['tutor_whatsapp' => '0881 193 707', 'whatsapp_community' => 'https://chat.whatsapp.com/AbC123']);
        self::assertSame([], $ok['errors']);
        self::assertSame('+265881193707', $ok['clean']['tutor_whatsapp']);
        $bad = SettingsService::validate(['tutor_whatsapp' => '555', 'whatsapp_community' => 'https://chat.whatsapp.com.evil.example/x']);
        self::assertSame(['tutor_whatsapp', 'whatsapp_community'], array_keys($bad['errors']));
        self::assertSame(['tutor_whatsapp' => '', 'whatsapp_community' => ''], SettingsService::validate([])['clean']);
    }

    public function testSeatPlan(): void
    {
        $default = new SeatChart(3, []);
        self::assertSame(24, $default->totalSeats());
        $plan = SeatChart::planFrom(['1' => ['female' => '5', 'male' => '99'], 2 => ['female' => '-1']], 3);
        self::assertSame(['female' => 5, 'male' => 30], $plan[1]);
        self::assertSame(['female' => 0, 'male' => 3], $plan[2]);
        $chart = SeatChart::fromIntake(['seats_per_group' => 3, 'seat_plan' => json_encode($plan)], [['form' => 1, 'gender' => 'male', 'status' => 'admitted', 'n' => 2]]);
        self::assertSame(5 + 30 + 0 + 3 + 12, $chart->totalSeats());
        self::assertSame(28, $chart->cell(1, 'male')['open']);
        self::assertFalse($chart->hasSeat(2, 'female'));
    }
}
