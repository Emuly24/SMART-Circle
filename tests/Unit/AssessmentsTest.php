<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Assessments\AssessmentService;
use SmartCircle\Application\Assessments\ExamService;

final class AssessmentsTest extends TestCase
{
    private const NOW = '2026-11-26 10:00:00';

    private static function a(array $over = []): array
    {
        return $over + ['kind' => 'exam', 'status' => 'published', 'opens_at' => null, 'closes_at' => null, 'results_at' => null];
    }

    public function testStateBeforeStarting(): void
    {
        self::assertSame('open', ExamService::state(self::a(), null, true, self::NOW));
        self::assertSame('locked', ExamService::state(self::a(), null, false, self::NOW), 'school has not taught a topic in it');
        self::assertSame('not_open', ExamService::state(self::a(['opens_at' => '2026-11-27 08:00:00']), null, true, self::NOW));
        self::assertSame('missed', ExamService::state(self::a(['closes_at' => '2026-11-25 17:00:00']), null, true, self::NOW));
        self::assertSame('missed', ExamService::state(self::a(['status' => 'closed']), null, true, self::NOW));
    }

    public function testStateAfterStarting(): void
    {
        self::assertSame('in_progress', ExamService::state(self::a(), ['status' => 'in_progress'], true, self::NOW));
        self::assertSame('submitted', ExamService::state(self::a(), ['status' => 'submitted'], true, self::NOW));
        self::assertSame('marked', ExamService::state(self::a(), ['status' => 'marked'], true, self::NOW), 'exam: wait for release');
        self::assertSame('results', ExamService::state(self::a(['results_at' => '2026-11-26 09:00:00']), ['status' => 'marked'], true, self::NOW));
        self::assertSame('results', ExamService::state(self::a(['kind' => 'weekly_check']), ['status' => 'marked'], true, self::NOW), 'exercise: instant');
        self::assertSame('submitted', ExamService::state(self::a(), ['status' => 'submitted'], false, self::NOW), 'a started attempt stays visible');
    }

    public function testDetailsValidation(): void
    {
        $ok = AssessmentService::validateDetails(['kind' => 'exam', 'title' => '  Form 3  Forces test ', 'form' => 3, 'subject' => 'physics',
            'opens_at' => '2026-12-01T08:00', 'closes_at' => '2026-12-01T12:00', 'duration_min' => 40]);
        self::assertSame([], $ok['errors']);
        self::assertSame('Form 3 Forces test', $ok['clean']['title']);
        self::assertSame('2026-12-01 08:00:00', $ok['clean']['opens_at']);

        $bad = AssessmentService::validateDetails(['kind' => 'exam', 'title' => 'x', 'form' => 0, 'subject' => 'art',
            'opens_at' => '2026-12-01T12:00', 'closes_at' => '2026-12-01T08:00', 'duration_min' => 0]);
        self::assertSame(['title', 'form', 'subject', 'closes_at', 'duration_min'], array_keys($bad['errors']));

        $assignment = AssessmentService::validateDetails(['kind' => 'assignment', 'title' => 'Essay', 'form' => 1, 'subject' => 'english', 'duration_min' => 30]);
        self::assertNull($assignment['clean']['duration_min'], 'only exams are timed');
    }

    public function testAllTopicsMustBeOpen(): void
    {
        $decide = static fn (int $t): array => ['open' => $t !== 7];
        self::assertTrue(AssessmentService::allOpen($decide, [1, 2]));
        self::assertFalse(AssessmentService::allOpen($decide, [1, 7]));
        self::assertTrue(AssessmentService::allOpen($decide, []), 'no topic: open');
    }
}
