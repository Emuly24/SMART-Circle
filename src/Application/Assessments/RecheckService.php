<?php

declare(strict_types=1);

namespace SmartCircle\Application\Assessments;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\AssessmentRepository;
use SmartCircle\Data\Repositories\RecheckRepository;
use SmartCircle\Data\Repositories\SubmissionRepository;

/**
 * "I think this deserves another look." Within 7 days of seeing their marks, a student may ask
 * for a re-check of one answer, with a clear reason. The tutor upholds or changes the mark with a
 * reply. Every request and decision is kept, which protects both the student and the tutor.
 */
final class RecheckService
{
    public const WINDOW_DAYS = 7;

    public function __construct(
        private readonly RecheckRepository $rechecks,
        private readonly SubmissionRepository $submissions,
        private readonly AssessmentRepository $assessments,
        private readonly NotificationService $notifications,
    ) {
    }

    /** When the student could first see the marks (start of the 7-day window). */
    public static function visibleSince(array $submission, array $assessment): ?string
    {
        if ($submission['status'] !== 'marked' || !ExamService::resultsVisible($assessment)) {
            return null;
        }
        $dates = array_filter([$submission['marked_at'] ?? null, $submission['submitted_at'] ?? null, $assessment['results_at'] ?? null]);

        return $dates === [] ? null : (string) max($dates);
    }

    public static function windowOpen(?string $since, ?int $now = null): bool
    {
        return $since !== null && (($now ?? time()) - (int) strtotime($since)) <= self::WINDOW_DAYS * 86400;
    }

    /** @return string error ('' when the request was sent) */
    public function request(int $assessmentId, int $userId, int $questionId, string $reason): string
    {
        $a = $this->assessments->find($assessmentId);
        $s = $this->submissions->forStudent($assessmentId, $userId);
        if ($a === null || $s === null) {
            return 'Not found.';
        }
        if (!self::windowOpen(self::visibleSince($s, $a))) {
            return 'Re-checks can be asked for within ' . self::WINDOW_DAYS . ' days of getting your marks.';
        }
        $question = null;
        foreach ($this->assessments->questions($assessmentId) as $q) {
            if ((int) $q['id'] === $questionId) {
                $question = $q;
            }
        }
        if ($question === null) {
            return 'That question is not part of this work.';
        }
        $answer = $this->submissions->answers((int) $s['id'])[$questionId] ?? [];
        $score = $answer['manual_score'] ?? $answer['auto_score'] ?? null;
        if ($score !== null && (float) $score >= (float) $question['max_marks']) {
            return 'This answer already has full marks.';
        }
        if ($this->rechecks->hasOpen((int) $s['id'], $questionId)) {
            return 'You have already asked for this answer to be re-checked. The tutor will reply soon.';
        }
        $reason = trim(str_replace("\r\n", "\n", $reason));
        if (mb_strlen($reason) < 15) {
            return 'Please explain clearly why you think your answer is correct (at least a sentence).';
        }
        $this->rechecks->create((int) $s['id'], $questionId, $userId, mb_substr($reason, 0, 2000), $score === null ? null : (float) $score);
        $this->notifications->notifyTutors('recheck', 'Re-check asked: ' . $a['title'],
            'A student believes an answer deserves another look.', '/studio/rechecks');

        return '';
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->rechecks->all();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $r = $this->rechecks->find($id);
        if ($r !== null) {
            $r['data'] = json_decode((string) $r['data'], true) ?: [];
        }

        return $r;
    }

    /** @return array<int, list<array<string, mixed>>> */
    public function history(int $submissionId): array
    {
        return $this->rechecks->forSubmission($submissionId);
    }

    public function countOpen(): int
    {
        return $this->rechecks->countOpen();
    }

    /** @return array<string, string> errors */
    public function resolve(int $id, string $decision, string $newScore, string $reply, int $tutorId): array
    {
        $r = $this->rechecks->find($id);
        if ($r === null || $r['status'] !== 'open') {
            return ['decision' => 'This request has already been answered.'];
        }
        $reply = trim($reply);
        $errors = [];
        if (!in_array($decision, ['upheld', 'changed'], true)) {
            $errors['decision'] = 'Choose whether the mark stays or changes.';
        }
        if (mb_strlen($reply) < 5) {
            $errors['tutor_reply'] = 'Explain your decision to the student.';
        }
        $score = null;
        if ($decision === 'changed') {
            $max = (float) $r['max_marks'];
            if (!is_numeric($newScore) || (float) $newScore < 0 || (float) $newScore > $max) {
                $errors['new_score'] = 'Give the new mark, from 0 to ' . AssessmentService::fmt($max) . '.';
            } else {
                $score = round((float) $newScore, 2);
            }
        }
        if ($errors !== []) {
            return $errors;
        }
        if ($score !== null) {
            $this->submissions->changeScore((int) $r['submission_id'], (int) $r['question_id'], $score);
        }
        $this->rechecks->resolve($id, $decision, $score, mb_substr($reply, 0, 2000), $tutorId);
        $this->notifications->notify([(int) $r['user_id']], 'recheck_answered',
            ($decision === 'changed' ? 'Mark changed: ' : 'Re-check answered: ') . $r['assessment_title'],
            'The tutor has replied to your re-check request.', '/assessments/' . $r['assessment_id'] . '/result');

        return [];
    }
}
