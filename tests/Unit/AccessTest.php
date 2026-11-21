<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Partners\SchoolService;
use SmartCircle\Application\Topics\AccessService;

/** The honesty rule: only what the student's own school has taught is open. */
final class AccessTest extends TestCase
{
    private const T = 10;   // a subtopic
    private const P = 1;    // its parent

    private static function d(array $cov = [], array $rep = [], array $exc = [], bool $reported = true): array
    {
        return AccessService::decide(self::T, self::P, $cov, $rep, $exc, $reported);
    }

    public function testSchoolReportOpensTopicAndParentCountsForSubtopics(): void
    {
        self::assertSame(['open' => true, 'basis' => 'school', 'state' => 'school'], self::d([self::T => 'covered']));
        self::assertTrue(self::d([self::P => 'covering'])['open'], 'teaching now counts');
        self::assertFalse(self::d([self::T => 'next'])['open'], 'next is not taught');
        self::assertSame('not_taught', self::d()['state']);
    }

    public function testMyClassHasNotReachedItAlwaysLocks(): void
    {
        $d = self::d([self::P => 'covered'], [self::T => ['status' => 'not_yet', 'review' => null]]);
        self::assertSame(['open' => false, 'basis' => null, 'state' => 'class_not_yet'], $d);
        $d = self::d([self::P => 'covered'], [self::T => ['status' => 'not_yet', 'review' => null]], [self::T => 'approved']);
        self::assertSame('exception', $d['basis'], 'only an approved early access overrides it');
    }

    public function testOwnReportOpensOnlyWhereNoTeacherReportsTheSubject(): void
    {
        $covered = [self::T => ['status' => 'covered', 'review' => null]];
        self::assertSame(['open' => true, 'basis' => 'self', 'state' => 'self_unverified'], self::d([], $covered, [], false));
        self::assertSame(['open' => false, 'basis' => null, 'state' => 'awaiting_check'], self::d([], $covered, [], true));
        $confirmed = [self::T => ['status' => 'covered', 'review' => 'confirmed']];
        self::assertSame('self_confirmed', self::d([], $confirmed, [], true)['state']);
        $rejected = [self::T => ['status' => 'covered', 'review' => 'rejected']];
        self::assertFalse(self::d([], $rejected, [], false)['open'], 'a rejected report counts for nothing');
    }

    public function testEarlyAccess(): void
    {
        self::assertSame('exception_pending', self::d([], [], [self::T => 'pending'])['state']);
        self::assertSame(['open' => true, 'basis' => 'exception', 'state' => 'exception'], self::d([], [], [self::P => 'approved']));
        self::assertSame('not_taught', self::d([], [], [self::T => 'declined'])['state']);
    }

    public function testReportGoesStaleAfterFourWeeks(): void
    {
        $now = strtotime('2026-11-21 12:00:00');
        self::assertFalse(SchoolService::freshnessOf('2026-10-30 12:00:00', $now)['stale']);
        self::assertTrue(SchoolService::freshnessOf('2026-10-24 12:00:00', $now)['stale']);
        self::assertSame(28, SchoolService::freshnessOf('2026-10-24 12:00:00', $now)['days']);
        self::assertTrue(SchoolService::freshnessOf(null, $now)['stale'], 'never reported');
        self::assertNull(SchoolService::freshnessOf('1000-01-01 00:00:00', $now)['last'], 'SQL placeholder means never');
    }
}
