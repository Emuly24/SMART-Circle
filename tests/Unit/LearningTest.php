<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Learning\FeedbackService;
use SmartCircle\Application\Learning\ImpactService;
use SmartCircle\Application\Learning\Quick5Service;

final class LearningTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function questions(): array
    {
        $out = [];
        foreach (['mathematics' => 1, 'physics' => 2, 'biology' => 3] as $subject => $topic) {
            for ($i = 1; $i <= 4; $i++) {
                $out[] = ['id' => $topic * 10 + $i, 'subject' => $subject, 'topic_id' => $topic];
            }
        }
        $out[] = ['id' => 99, 'subject' => 'chemistry', 'topic_id' => 9];

        return $out;
    }

    public function testPickHonoursTheHonestyRuleAndSpreadsSubjects(): void
    {
        $open = static fn (int $topic): bool => $topic !== 9;
        $set = Quick5Service::pick(self::questions(), $open, [], 'seed');
        self::assertCount(5, $set);
        self::assertNotContains(99, array_column($set, 'id'));
        self::assertGreaterThanOrEqual(3, count(array_unique(array_column($set, 'subject'))));
        // Stable for the same seed; different for another week.
        self::assertSame(array_column($set, 'id'), array_column(Quick5Service::pick(self::questions(), $open, [], 'seed'), 'id'));
        // Recently answered questions are left out.
        $recent = array_column($set, 'id');
        self::assertSame([], array_intersect($recent, array_column(Quick5Service::pick(self::questions(), $open, $recent, 'seed'), 'id')));
        self::assertSame([], Quick5Service::pick(self::questions(), static fn (): bool => false, [], 'seed'));
    }

    public function testStreak(): void
    {
        $now = (int) strtotime('2026-11-27 10:00');
        $w = static fn (string $d): string => Quick5Service::week((int) strtotime($d));
        self::assertSame(0, Quick5Service::streak([], $now));
        self::assertSame(3, Quick5Service::streak([$w('2026-11-27') => 5, $w('2026-11-20') => 5, $w('2026-11-13') => 7], $now));
        // This week not finished yet: the streak still counts from last week.
        self::assertSame(2, Quick5Service::streak([$w('2026-11-27') => 2, $w('2026-11-20') => 5, $w('2026-11-13') => 5], $now));
        self::assertSame(1, Quick5Service::streak([$w('2026-11-27') => 5, $w('2026-11-13') => 5], $now));
    }

    public function testRightAnswerInWords(): void
    {
        self::assertSame('4', Quick5Service::rightAnswer('mcq', ['options' => ['3', '4'], 'correct' => 1]));
        self::assertSame('2.5 m/s', Quick5Service::rightAnswer('numeric', ['answer' => 2.5, 'unit' => 'm/s']));
        self::assertSame('went', Quick5Service::rightAnswer('grammar_fill', ['accepted' => ['went', 'had gone']]));
    }

    public function testFeedbackStaysAnonymousInSmallGroups(): void
    {
        $two = [['form' => 2, 'clarity' => 4, 'pace' => 'fast', 'helpful' => 'Examples', 'change_one' => 'Slower']] + [1 => ['form' => 2, 'clarity' => 2, 'pace' => 'right', 'helpful' => null, 'change_one' => null]];
        $s = FeedbackService::summarise(array_values($two));
        self::assertSame(2, $s['n']);
        self::assertFalse($s['enough']);
        self::assertSame([], $s['helpful']);
        self::assertSame([], $s['forms']);
        self::assertSame(3.0, $s['clarity']);
        $three = array_merge(array_values($two), [['form' => 3, 'clarity' => 5, 'pace' => 'right', 'helpful' => 'Notes', 'change_one' => null]]);
        $s = FeedbackService::summarise($three);
        self::assertTrue($s['enough']);
        self::assertSame(['Examples', 'Notes'], $s['helpful']);
        self::assertSame([], $s['forms'], 'No form has 3 answers yet');
        self::assertSame(['slow' => 0, 'right' => 2, 'fast' => 1], $s['pace']);
        self::assertSame(['clarity', 'pace'], array_keys(FeedbackService::validate([])['errors']));
    }

    public function testImpactSummary(): void
    {
        $s = ImpactService::summarise([
            ['form' => 2, 'entry' => 60.0, 'now' => 70.0],
            ['form' => 2, 'entry' => 80.0, 'now' => 75.0],
            ['form' => 3, 'entry' => null, 'now' => 50.0],
        ]);
        self::assertSame(2, $s['compared']);
        self::assertSame(1, $s['up']);
        self::assertSame(['students' => 2, 'entry' => 70.0, 'now' => 72.5], $s['forms'][2]);
        self::assertNull($s['forms'][3]['entry']);
    }
}
