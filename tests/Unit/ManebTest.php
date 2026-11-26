<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Alumni\AlumniService;
use SmartCircle\Application\Alumni\YearEndService;
use SmartCircle\Application\Calendar\RevisionMode;
use SmartCircle\Application\Maneb\ManebService;
use SmartCircle\Application\OfficeHours\OfficeHoursService;
use SmartCircle\Application\Topics\AccessService;

final class ManebTest extends TestCase
{
    /** The 2027 MANEB dates from the Ministry calendar. */
    private const EVENTS = [
        ['kind' => 'jce', 'title' => 'MANEB JCE', 'forms' => '2', 'school_id' => null, 'starts_on' => '2027-05-24', 'ends_on' => '2027-06-02'],
        ['kind' => 'msce', 'title' => 'MANEB MSCE', 'forms' => '4', 'school_id' => null, 'starts_on' => '2027-06-21', 'ends_on' => '2027-07-12'],
        ['kind' => 'midterm', 'title' => 'Mid-term', 'forms' => null, 'school_id' => null, 'starts_on' => '2027-02-15', 'ends_on' => '2027-02-19'],
    ];

    public function testRevisionOpensThreeWeeksBeforeAndClosesAfterTheExam(): void
    {
        self::assertNull(RevisionMode::window(2, self::EVENTS, '2027-05-02'));
        $w = RevisionMode::window(2, self::EVENTS, '2027-05-03');
        self::assertSame('jce', $w['exam']);
        self::assertSame([1, 2], $w['forms']);
        self::assertSame(21, $w['days']);
        self::assertSame(0, RevisionMode::window(2, self::EVENTS, '2027-06-02')['days']);
        self::assertNull(RevisionMode::window(2, self::EVENTS, '2027-06-03'));
        self::assertSame([3, 4], RevisionMode::window(4, self::EVENTS, '2027-06-01')['forms']);
        // Forms 1 and 3 never revise; a school's own event never opens revision.
        self::assertNull(RevisionMode::window(1, self::EVENTS, '2027-05-25'));
        self::assertNull(RevisionMode::window(3, self::EVENTS, '2027-06-25'));
        $school = [['kind' => 'msce', 'title' => 'x', 'forms' => '4', 'school_id' => 3, 'starts_on' => '2027-01-10', 'ends_on' => '2027-01-12']];
        self::assertNull(RevisionMode::window(4, $school, '2027-01-05'));
    }

    public function testRevisionOnlyOpensLockedSyllabusTopics(): void
    {
        $locked = ['open' => false, 'basis' => null, 'state' => 'not_taught'];
        $school = ['open' => true, 'basis' => 'school', 'state' => 'school'];
        self::assertSame('revision', AccessService::withRevisionFor($locked, 1, [1, 2])['basis']);
        self::assertSame($school, AccessService::withRevisionFor($school, 2, [1, 2]));
        self::assertSame($locked, AccessService::withRevisionFor($locked, 3, [1, 2]));
        self::assertSame($locked, AccessService::withRevisionFor($locked, 2, []));
        // Revision does not count as "taught" for the tutor's readiness figures.
        self::assertFalse(AccessService::taught(['open' => true, 'basis' => 'revision', 'state' => 'revision']));
        self::assertTrue(AccessService::taught($school));
    }

    public function testProfileGetsRevisionForms(): void
    {
        $p = (new RevisionMode())->apply(['form' => 4, 'track' => 'science']);
        self::assertSame([4], $p['forms']);
        self::assertNull($p['revision']);
    }

    public function testPacing(): void
    {
        $rows = [['lessons' => 10, 'watched' => 4], ['lessons' => 3, 'watched' => 3]];
        self::assertSame(['left' => 6, 'per_day' => 2], ManebService::pacing($rows, 3));
        self::assertSame(['left' => 6, 'per_day' => 6], ManebService::pacing($rows, 0));
    }

    public function testWhoRecordsWhichResults(): void
    {
        self::assertSame([], ManebService::examsFor('student', ['form' => 1]));
        self::assertSame(['jce'], ManebService::examsFor('student', ['form' => 3]));
        self::assertSame(['jce', 'msce'], ManebService::examsFor('student', ['form' => 4]));
        self::assertSame(['jce', 'msce'], ManebService::examsFor('alumni', ['form' => 4]));
        self::assertSame([], ManebService::examsFor('partner', ['form' => 4]));
    }

    public function testResultsValidation(): void
    {
        $subjects = ['mathematics', 'biology', 'english'];
        $ok = ManebService::validate(['exam' => 'msce', 'year' => '2027', 'grades' => ['mathematics' => '2', 'biology' => ' 5 ', 'physics' => '1'],
            'subjects_sat' => '8', 'subjects_passed' => '7', 'points' => '24', 'share' => 'summary'], $subjects, ['jce', 'msce'], 2027);
        self::assertSame([], $ok['errors']);
        self::assertSame('{"mathematics":"2","biology":"5"}', $ok['clean']['grades']);
        self::assertSame(24, $ok['clean']['points']);
        $bad = ManebService::validate(['exam' => 'jce', 'year' => '2028', 'grades' => ['mathematics' => '3'], 'subjects_sat' => '5', 'subjects_passed' => '6'],
            $subjects, ['jce'], 2027);
        self::assertArrayHasKey('year', $bad['errors']);
        self::assertArrayHasKey('grades', $bad['errors']);
        self::assertArrayHasKey('subjects_passed', $bad['errors']);
        self::assertNull(ManebService::validate(['exam' => 'jce', 'year' => '2027', 'grades' => ['english' => 'b'], 'points' => '20'], $subjects, ['jce'], 2027)['clean']['points']);
        self::assertArrayHasKey('exam', ManebService::validate(['exam' => 'msce', 'year' => '2027', 'grades' => ['english' => '1']], $subjects, ['jce'], 2027)['errors']);
        self::assertSame('private', ManebService::validate(['exam' => 'jce', 'year' => '2027', 'grades' => ['english' => 'A'], 'share' => 'everyone'], $subjects, ['jce'], 2027)['clean']['share']);
    }

    public function testSummaryNamesOnlyCheckedVolunteers(): void
    {
        $rows = [
            ['exam' => 'msce', 'year' => 2027, 'grades' => '{"mathematics":"2","english":"9"}', 'share' => 'named', 'verified_at' => '2027-09-01', 'full_name' => 'Ada', 'reflection' => 'Practise daily', 'points' => 20],
            ['exam' => 'msce', 'year' => 2027, 'grades' => '{"mathematics":"7"}', 'share' => 'named', 'verified_at' => null, 'full_name' => 'Ben', 'reflection' => null, 'points' => null],
            ['exam' => 'jce', 'year' => 2027, 'grades' => '{"biology":"B"}', 'share' => 'summary', 'verified_at' => '2027-08-01', 'full_name' => 'Cleo', 'reflection' => null, 'points' => null],
        ];
        $s = ManebService::summary($rows);
        self::assertSame('msce', $s[0]['exam']);
        self::assertSame(2, $s[0]['students']);
        self::assertSame(1, $s[0]['verified']);
        self::assertSame(['2' => 1, '7' => 1], $s[0]['subjects']['mathematics']['grades']);
        self::assertSame(2, $s[0]['subjects']['mathematics']['passed']);
        self::assertSame(0, $s[0]['subjects']['english']['passed']);
        self::assertSame(['Ada'], array_column($s[0]['named'], 'name'));
        self::assertSame([], $s[1]['named']);
        self::assertTrue(ManebService::passes('jce', 'D'));
        self::assertFalse(ManebService::passes('jce', 'F'));
        self::assertTrue(ManebService::passes('msce', '8'));
        self::assertFalse(ManebService::passes('msce', '9'));
    }

    public function testYearEnd(): void
    {
        self::assertSame('promote', YearEndService::suggested(2));
        self::assertSame('graduate', YearEndService::suggested(4));
        self::assertSame(['error' => null, 'form' => 2, 'track' => 'general'], YearEndService::decide(['form' => 1, 'track' => 'general'], ['action' => 'promote']));
        self::assertNotNull(YearEndService::decide(['form' => 2, 'track' => 'general'], ['action' => 'promote'])['error']);
        self::assertSame(['error' => null, 'form' => 3, 'track' => 'humanities'], YearEndService::decide(['form' => 2, 'track' => 'general'], ['action' => 'promote', 'track' => 'humanities']));
        self::assertSame(['error' => null, 'form' => 4, 'track' => 'science'], YearEndService::decide(['form' => 3, 'track' => 'science'], ['action' => 'promote']));
        self::assertNotNull(YearEndService::decide(['form' => 3, 'track' => 'science'], ['action' => 'graduate'])['error']);
        self::assertNotNull(YearEndService::decide(['form' => 4, 'track' => 'science'], ['action' => 'promote'])['error']);
        self::assertNull(YearEndService::decide(['form' => 4, 'track' => 'science'], ['action' => 'graduate'])['error']);
        self::assertSame(3, YearEndService::decide(['form' => 3, 'track' => 'science'], ['action' => 'stay'])['form']);
    }

    public function testOfficeHours(): void
    {
        $now = '2026-11-26 10:00:00';
        $ok = OfficeHoursService::validate(['date' => '2026-11-28', 'start' => '16:00', 'end' => '17:30', 'mode' => 'phone', 'capacity' => '4', 'audience' => 'form:4'], $now);
        self::assertSame([], $ok['errors']);
        self::assertSame('2026-11-28 16:00:00', $ok['clean']['starts_at']);
        $past = OfficeHoursService::validate(['date' => '2026-11-25', 'start' => '16:00', 'end' => '17:00', 'mode' => 'phone', 'capacity' => '4'], $now);
        self::assertArrayHasKey('date', $past['errors']);
        $long = OfficeHoursService::validate(['date' => '2026-11-28', 'start' => '08:00', 'end' => '12:00', 'mode' => 'fax', 'capacity' => '0'], $now);
        self::assertSame(['end', 'mode', 'capacity'], array_keys($long['errors']));
        self::assertArrayHasKey('end', OfficeHoursService::validate(['date' => '2026-11-28', 'start' => '17:00', 'end' => '16:00', 'mode' => 'phone', 'capacity' => '2'], $now)['errors']);
        self::assertTrue(OfficeHoursService::eligible('all', 'student', 1));
        self::assertTrue(OfficeHoursService::eligible('form:4', 'student', 4));
        self::assertFalse(OfficeHoursService::eligible('form:4', 'student', 3));
        self::assertFalse(OfficeHoursService::eligible('all', 'alumni', null));
        self::assertTrue(OfficeHoursService::eligible('alumni', 'alumni', null));
        self::assertFalse(OfficeHoursService::eligible('alumni', 'student', 4));
    }

    public function testCommunityAccess(): void
    {
        self::assertTrue(AlumniService::mayRead('alumni', null));
        self::assertTrue(AlumniService::mayRead('student', 3));
        self::assertFalse(AlumniService::mayRead('student', 2));
        self::assertFalse(AlumniService::mayRead('partner', null));
        self::assertTrue(AlumniService::mayWrite('tutor'));
        self::assertFalse(AlumniService::mayWrite('student'));
    }
}
