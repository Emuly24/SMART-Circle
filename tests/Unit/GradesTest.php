<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Calendar\CalendarService;
use SmartCircle\Application\Grades\GradeService;

final class GradesTest extends TestCase
{
    /** The Ministry of Education's 2026/27 calendar. */
    private const TERMS = [
        ['id' => 1, 'number' => 1, 'year_name' => '2026/27', 'opens_on' => '2026-09-14', 'closes_on' => '2026-12-18'],
        ['id' => 2, 'number' => 2, 'year_name' => '2026/27', 'opens_on' => '2027-01-04', 'closes_on' => '2027-03-25'],
        ['id' => 3, 'number' => 3, 'year_name' => '2026/27', 'opens_on' => '2027-04-12', 'closes_on' => '2027-07-16'],
    ];
    private const MSCE = [
        ['grade' => '1', 'min_percent' => 75, 'descriptor' => 'Distinction'], ['grade' => '2', 'min_percent' => 70, 'descriptor' => 'Distinction'],
        ['grade' => '3', 'min_percent' => 65, 'descriptor' => 'Strong credit'], ['grade' => '4', 'min_percent' => 60, 'descriptor' => 'Credit'],
        ['grade' => '5', 'min_percent' => 55, 'descriptor' => 'Credit'], ['grade' => '6', 'min_percent' => 50, 'descriptor' => 'Credit'],
        ['grade' => '7', 'min_percent' => 45, 'descriptor' => 'Pass'], ['grade' => '8', 'min_percent' => 40, 'descriptor' => 'Pass'],
        ['grade' => '9', 'min_percent' => 0, 'descriptor' => 'Not yet a pass'],
    ];

    public function testTermWeekAndHolidays(): void
    {
        $p = CalendarService::position('2026-11-26', self::TERMS);
        self::assertTrue($p['in_term']);
        self::assertSame(1, (int) $p['term']['number']);
        self::assertSame(11, $p['week']);
        self::assertSame(14, $p['weeks'], 'Term 1 has 14 weeks');
        self::assertSame(12, CalendarService::position('2027-02-01', self::TERMS)['weeks'], 'Term 2 has 12 weeks');

        self::assertTrue(CalendarService::holiday('2026-12-28', self::TERMS), 'Christmas holiday');
        self::assertTrue(CalendarService::holiday('2027-04-01', self::TERMS), 'Easter holiday');
        self::assertTrue(CalendarService::holiday('2027-08-01', self::TERMS), 'long holiday after Term 3');
        self::assertFalse(CalendarService::holiday('2026-10-05', self::TERMS));
        self::assertFalse(CalendarService::holiday('2026-08-01', self::TERMS), 'before the calendar starts: unknown');
        self::assertFalse(CalendarService::holiday('2026-12-28', []), 'no calendar: unknown');
        $p = CalendarService::position('2026-12-28', self::TERMS);
        self::assertSame(2, (int) $p['next']['number']);
    }

    public function testHolidayWorkCountsForTheTermJustFinished(): void
    {
        self::assertSame(['2026-09-14 00:00:00', '2027-01-04 00:00:00'], CalendarService::gradingRange(1, self::TERMS));
        self::assertSame(['2027-04-12 00:00:00', '2027-09-24 00:00:00'], CalendarService::gradingRange(3, self::TERMS));
    }

    public function testManebCountdownOnlyForTheRightForm(): void
    {
        $events = [
            ['kind' => 'jce', 'title' => 'JCE', 'starts_on' => '2027-05-24', 'ends_on' => '2027-06-02', 'forms' => '2'],
            ['kind' => 'msce', 'title' => 'MSCE', 'starts_on' => '2027-06-21', 'ends_on' => '2027-07-12', 'forms' => '4'],
            ['kind' => 'midterm', 'title' => 'Mid-term', 'starts_on' => '2027-02-15', 'ends_on' => '2027-02-19', 'forms' => null],
        ];
        $f2 = CalendarService::manebFor(2, $events, '2027-05-14');
        self::assertCount(1, $f2);
        self::assertSame(10, $f2[0]['days']);
        self::assertSame('MSCE', CalendarService::manebFor(4, $events, '2027-05-14')[0]['title']);
        self::assertSame([], CalendarService::manebFor(3, $events, '2027-05-14'));
        self::assertSame([], CalendarService::manebFor(2, $events, '2027-06-03'), 'finished exams drop off');
    }

    public function testCategoriesAreAveragedThenWeighted(): void
    {
        $work = [
            ['kind' => 'weekly_check', 'score' => 8, 'max_score' => 10],   // exercise 80
            ['kind' => 'note_check', 'score' => 6, 'max_score' => 10],     // exercise 60 -> avg 70
            ['kind' => 'assignment', 'score' => 15, 'max_score' => 20],    // 75
            ['kind' => 'test', 'score' => 30, 'max_score' => 50],          // 60
            ['kind' => 'exam', 'score' => 50, 'max_score' => 100],         // 50
        ];
        $c = GradeService::compute($work, GradeService::DEFAULT_WEIGHTS);
        self::assertSame(70.0, $c['categories']['exercise']['percent']);
        self::assertSame(2, $c['categories']['exercise']['count']);
        // 70*.15 + 75*.20 + 60*.25 + 50*.40 = 10.5 + 15 + 15 + 20 = 60.5
        self::assertSame(60.5, $c['percent']);
    }

    public function testMissingCategoriesAreReweighted(): void
    {
        // Early in term: only exercises (70) and one assignment (80): (70*15 + 80*20) / 35 = 75.7
        $c = GradeService::compute([
            ['kind' => 'weekly_check', 'score' => 7, 'max_score' => 10],
            ['kind' => 'assignment', 'score' => 8, 'max_score' => 10],
        ], GradeService::DEFAULT_WEIGHTS);
        self::assertSame(75.7, $c['percent']);
        self::assertNull(GradeService::compute([], GradeService::DEFAULT_WEIGHTS)['percent'], 'no work yet: no grade');
    }

    public function testBandsAndScales(): void
    {
        self::assertSame('jce', GradeService::scaleFor(2));
        self::assertSame('msce', GradeService::scaleFor(3));
        self::assertSame(['grade' => '1', 'descriptor' => 'Distinction'], GradeService::grade(75.0, self::MSCE));
        self::assertSame('6', GradeService::grade(50.0, self::MSCE)['grade']);
        self::assertSame('Not yet a pass', GradeService::grade(12.0, self::MSCE)['descriptor']);
        self::assertNull(GradeService::grade(null, self::MSCE));
        self::assertSame('exercise', GradeService::categoryOf('note_check'));
    }
}
