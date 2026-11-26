<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Assessments\Feedback;
use SmartCircle\Application\Assessments\RecheckService;
use SmartCircle\Application\Notes\NoteService;

final class NotesTest extends TestCase
{
    private const BLOCKS = [
        ['id' => 1, 'type' => 'text', 'assessment_id' => null],
        ['id' => 2, 'type' => 'image', 'assessment_id' => null],
        ['id' => 3, 'type' => 'checkpoint', 'assessment_id' => 30],
        ['id' => 4, 'type' => 'text', 'assessment_id' => null],
        ['id' => 5, 'type' => 'checkpoint', 'assessment_id' => 50],
        ['id' => 6, 'type' => 'text', 'assessment_id' => null],
        ['id' => 7, 'type' => 'checkpoint', 'assessment_id' => 70],
    ];

    public function testReadingStopsAtTheFirstUnfinishedCheckpoint(): void
    {
        $g = NoteService::gate(self::BLOCKS, []);
        self::assertSame([1, 2, 3], array_column($g['visible'], 'id'), 'later parts are not even sent');
        self::assertSame(4, $g['hidden']);
        self::assertSame(3, $g['active']);
    }

    public function testSubmittingOpensTheNextPart(): void
    {
        $g = NoteService::gate(self::BLOCKS, [30 => 'submitted']);
        self::assertSame([1, 2, 3, 4, 5], array_column($g['visible'], 'id'));
        self::assertSame(5, $g['active']);

        $all = NoteService::gate(self::BLOCKS, [30 => 'submitted', 50 => 'submitted', 70 => 'submitted']);
        self::assertSame(7, count($all['visible']));
        self::assertNull($all['active']);
        self::assertSame(0, $all['hidden']);
    }

    public function testSkippingAheadDoesNotHelp(): void
    {
        // Submitting a later checkpoint (somehow) never opens anything past the first unfinished one.
        $g = NoteService::gate(self::BLOCKS, [50 => 'submitted']);
        self::assertSame([1, 2, 3], array_column($g['visible'], 'id'));
    }

    public function testEncouragingStatuses(): void
    {
        self::assertSame('got_it', Feedback::status(3, 3));
        self::assertSame('partly', Feedback::status(1.5, 3));
        self::assertSame('not_yet', Feedback::status(0, 3));
        self::assertSame(['↻', 'Not yet'], Feedback::STATUSES['not_yet']);
        self::assertStringNotContainsStringIgnoringCase('wrong', implode(' ', array_map(fn ($s) => $s[1], Feedback::STATUSES)));
        self::assertStringContainsString('Thank you for trying', Feedback::headline(0, 10));
    }

    public function testRecheckWindowIsSevenDaysFromSeeingTheMarks(): void
    {
        $sub = ['status' => 'marked', 'marked_at' => '2026-11-20 10:00:00', 'submitted_at' => '2026-11-19 09:00:00'];
        $exam = ['kind' => 'exam', 'results_at' => '2026-11-22 08:00:00'];
        self::assertSame('2026-11-22 08:00:00', RecheckService::visibleSince($sub, $exam), 'exam: from the release');
        self::assertTrue(RecheckService::windowOpen('2026-11-22 08:00:00', strtotime('2026-11-29 07:00:00')));
        self::assertFalse(RecheckService::windowOpen('2026-11-22 08:00:00', strtotime('2026-11-29 09:00:00')));
        self::assertNull(RecheckService::visibleSince($sub, ['kind' => 'exam', 'results_at' => null]), 'not released yet');
        self::assertSame('2026-11-20 10:00:00', RecheckService::visibleSince($sub, ['kind' => 'note_check', 'results_at' => null]), 'checkpoint: once marked');
    }
}
