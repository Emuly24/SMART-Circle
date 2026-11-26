<?php

declare(strict_types=1);

namespace SmartCircle\Application\Assessments;

use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Data\Repositories\AssessmentRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Data\Repositories\SubmissionRepository;
use SmartCircle\Support\Subjects;

/**
 * Students taking exams, assignments and exercises.
 *  - Only for their form and subjects, and only when their school has taught every
 *    topic in it (the honesty rule).
 *  - One attempt. Exams have a server-side timer: answers arriving more than
 *    GRACE_SECONDS after the deadline are refused (the last saved answers count).
 *  - Exercises show marks as soon as they are fully marked; exams and assignments
 *    when the tutor releases results.
 */
final class ExamService
{
    public const GRACE_SECONDS = 120;

    public function __construct(
        private readonly AssessmentRepository $assessments,
        private readonly SubmissionRepository $submissions,
        private readonly StudentRepository $students,
        private readonly AccessService $access,
    ) {
    }

    /**
     * Where an assessment stands for one student.
     * @param array<string, mixed> $a assessment
     * @param array<string, mixed>|null $submission
     * @return string locked | not_open | open | in_progress | submitted | marked | results | missed
     */
    public static function state(array $a, ?array $submission, bool $topicsOpen, string $now): string
    {
        if ($submission !== null) {
            if ($submission['status'] === 'in_progress') {
                return self::isClosed($a, $now) ? 'missed' : 'in_progress';
            }
            if ($submission['status'] === 'marked') {
                return self::resultsVisible($a) ? 'results' : 'marked';
            }

            return 'submitted';
        }
        if (!$topicsOpen) {
            return 'locked';
        }
        if ($a['opens_at'] !== null && (string) $a['opens_at'] > $now) {
            return 'not_open';
        }

        return self::isClosed($a, $now) ? 'missed' : 'open';
    }

    /** @param array<string, mixed> $a */
    private static function isClosed(array $a, string $now): bool
    {
        return $a['status'] === 'closed' || ($a['closes_at'] !== null && (string) $a['closes_at'] <= $now);
    }

    /** @param array<string, mixed> $a */
    public static function resultsVisible(array $a): bool
    {
        return $a['kind'] === 'weekly_check' || $a['results_at'] !== null;
    }

    /**
     * Published assessments for the student, each with 'state'.
     * @return list<array<string, mixed>>
     */
    public function listFor(int $userId): array
    {
        $profile = $this->students->profile($userId);
        $decide = $this->access->forStudent($userId);
        if ($profile === null || $decide === null) {
            return [];
        }
        $mine = $this->submissions->allForStudent($userId);
        $now = date('Y-m-d H:i:s');
        $out = [];
        foreach ($this->assessments->publishedForForm((int) $profile['form']) as $a) {
            if (!in_array($a['subject'], Subjects::forTrack((string) $profile['track']), true)) {
                continue;
            }
            $open = AssessmentService::allOpen($decide, $this->assessments->topicIds((int) $a['id']));
            $a['state'] = self::state($a, $mine[(int) $a['id']] ?? null, $open, $now);
            $a['submission'] = $mine[(int) $a['id']] ?? null;
            $out[] = $a;
        }

        return $out;
    }

    /**
     * One assessment for the student, or null if it is not theirs to see.
     * @return array{assessment: array<string, mixed>, state: string, submission: ?array<string, mixed>}|null
     */
    public function forStudent(int $id, int $userId): ?array
    {
        foreach ($this->listFor($userId) as $a) {
            if ((int) $a['id'] === $id) {
                return ['assessment' => $a, 'state' => $a['state'], 'submission' => $a['submission']];
            }
        }

        return null;
    }

    /** @return string error ('' when started or already in progress) */
    public function start(int $id, int $userId): string
    {
        $v = $this->forStudent($id, $userId);
        if ($v === null) {
            return 'Not available.';
        }
        if ($v['state'] === 'in_progress') {
            return '';
        }
        if ($v['state'] !== 'open') {
            return 'This cannot be started now.';
        }
        $this->submissions->start($id, $userId, $v['assessment']['duration_min'] !== null ? (int) $v['assessment']['duration_min'] : null);

        return '';
    }

    /**
     * The attempt in progress: questions WITHOUT the answers, the student's saved answers and time left.
     * @return array<string, mixed>|null
     */
    public function attempt(int $id, int $userId): ?array
    {
        $v = $this->forStudent($id, $userId);
        if ($v === null || $v['state'] !== 'in_progress') {
            return null;
        }
        $sub = (array) $v['submission'];
        $questions = array_map(static function (array $q): array {
            // Never send correct answers to the browser.
            $q['data'] = $q['type'] === 'mcq' ? ['options' => $q['data']['options'] ?? []]
                : ($q['type'] === 'numeric' ? ['unit' => $q['data']['unit'] ?? ''] : []);

            return $q;
        }, $this->assessments->questions($id));

        return [
            'assessment' => $v['assessment'],
            'submission' => $sub,
            'questions' => $questions,
            'answers' => array_map(static fn (array $r): string => (string) $r['answer'], $this->submissions->answers((int) $sub['id'])),
            'seconds_left' => $this->submissions->secondsLeft((int) $sub['id']),
        ];
    }

    /**
     * Save answers (and submit when $final). Late answers are refused; the saved ones count.
     * @param array<string, mixed> $posted question id => answer
     * @return array{error: string, late: bool, state: ?string}
     */
    public function save(int $id, int $userId, array $posted, bool $final): array
    {
        $v = $this->forStudent($id, $userId);
        if ($v === null || $v['submission'] === null || $v['submission']['status'] !== 'in_progress') {
            return ['error' => 'There is no attempt in progress.', 'late' => false, 'state' => null];
        }
        $subId = (int) $v['submission']['id'];
        $questions = $this->assessments->questions($id);
        $late = $this->submissions->isPastDeadline($subId, self::GRACE_SECONDS);
        $closed = $v['state'] === 'missed';

        if (!$late && !$closed) {
            $clean = [];
            foreach ($questions as $q) {
                $qid = (int) $q['id'];
                if (array_key_exists($qid, $posted) && is_scalar($posted[$qid])) {
                    $clean[$qid] = mb_substr(trim((string) $posted[$qid]), 0, 10000);
                }
            }
            $this->submissions->saveAnswers($subId, $clean);
        }
        if (!$final && !$late && !$closed) {
            return ['error' => '', 'late' => false, 'state' => 'in_progress'];
        }

        // Submit (also when late: what was saved in time is marked).
        $saved = $this->submissions->answers($subId);
        $scores = [];
        $score = $max = 0.0;
        $complete = true;
        foreach ($questions as $q) {
            $qid = (int) $q['id'];
            $m = (float) $q['max_marks'];
            $max += $m;
            $s = Marker::score((string) $q['type'], (array) $q['data'], (string) ($saved[$qid]['answer'] ?? ''), $m);
            $scores[$qid] = $s;
            $s === null ? $complete = false : $score += $s;
        }
        $this->submissions->submit($subId, $scores, round($score, 2), $max, $complete);
        $state = $complete && $v['assessment']['kind'] === 'weekly_check' ? 'results' : ($complete ? 'marked' : 'submitted');

        return ['error' => '', 'late' => $late || $closed, 'state' => $state];
    }

    /**
     * The student's marked work, once results are visible.
     * @return array<string, mixed>|null
     */
    public function result(int $id, int $userId): ?array
    {
        $v = $this->forStudent($id, $userId);
        if ($v === null || $v['state'] !== 'results') {
            return null;
        }

        return [
            'assessment' => $v['assessment'],
            'submission' => $v['submission'],
            'questions' => $this->assessments->questions($id),
            'answers' => $this->submissions->answers((int) $v['submission']['id']),
        ];
    }
}
