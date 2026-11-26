<?php

declare(strict_types=1);

namespace SmartCircle\Application\Assessments;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Data\Repositories\AssessmentRepository;
use SmartCircle\Data\Repositories\QuestionRepository;
use SmartCircle\Data\Repositories\SubmissionRepository;
use SmartCircle\Support\Subjects;

/** Tutor: the question bank, building assessments, marking and releasing results. */
final class AssessmentService
{
    public const KINDS = [
        'exam' => 'End-of-term exam (timed)',
        'test' => 'Test (timed)',
        'assignment' => 'Assignment',
        'weekly_check' => 'Exercise (instant marks)',
    ];

    /** Kinds that need a time limit. */
    public const TIMED = ['exam', 'test'];

    public function __construct(
        private readonly QuestionRepository $questions,
        private readonly AssessmentRepository $assessments,
        private readonly SubmissionRepository $submissions,
        private readonly NotificationService $notifications,
        private readonly AccessService $access,
    ) {
    }

    /* ---------- Question bank ---------- */

    /** @return list<array<string, mixed>> */
    public function bank(int $form, string $subject): array
    {
        return $this->questions->forFormSubject($form, $subject);
    }

    /** @return array<string, mixed>|null */
    public function question(int $id): ?array
    {
        return $this->questions->find($id);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: ?int}
     */
    public function saveQuestion(?int $id, array $input, int $tutorId): array
    {
        $v = QuestionForm::validate($input);
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'id' => null];
        }
        if ($id === null) {
            return ['errors' => [], 'id' => $this->questions->create($v['clean'], $tutorId)];
        }
        $this->questions->update($id, $v['clean']);

        return ['errors' => [], 'id' => $id];
    }

    public function deleteQuestion(int $id): bool
    {
        $q = $this->questions->find($id);
        foreach ($this->questions->forFormSubject((int) ($q['form'] ?? 0), (string) ($q['subject'] ?? '')) as $row) {
            if ((int) $row['id'] === $id && (int) $row['used'] > 0) {
                return false;   // still in an assessment: remove it there first
            }
        }
        $this->questions->delete($id);

        return true;
    }

    /* ---------- Assessments ---------- */

    /**
     * @param array<string, mixed> $in kind, title, form, subject, instructions, opens_at, closes_at, duration_min
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validateDetails(array $in): array
    {
        $kind = (string) ($in['kind'] ?? '');
        $title = trim((string) preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')));
        $form = (int) ($in['form'] ?? 0);
        $subject = (string) ($in['subject'] ?? '');
        $opens = self::dateTime((string) ($in['opens_at'] ?? ''));
        $closes = self::dateTime((string) ($in['closes_at'] ?? ''));
        $duration = (int) ($in['duration_min'] ?? 0);
        $errors = [];

        if (!isset(self::KINDS[$kind])) {
            $errors['kind'] = 'Choose exam, assignment or exercise.';
        }
        if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
            $errors['title'] = 'Give it a title (3–200 characters).';
        }
        if ($form < 1 || $form > 4) {
            $errors['form'] = 'Choose the form.';
        }
        if (!isset(Subjects::NAMES[$subject])) {
            $errors['subject'] = 'Choose the subject.';
        }
        if (($in['opens_at'] ?? '') !== '' && $opens === null) {
            $errors['opens_at'] = 'Check the date and time.';
        }
        if (($in['closes_at'] ?? '') !== '' && $closes === null) {
            $errors['closes_at'] = 'Check the date and time.';
        }
        if ($opens !== null && $closes !== null && $closes <= $opens) {
            $errors['closes_at'] = 'It must close after it opens.';
        }
        if (in_array($kind, self::TIMED, true) && ($duration < 5 || $duration > 240)) {
            $errors['duration_min'] = 'An exam or test needs a time limit between 5 and 240 minutes.';
        }

        return ['errors' => $errors, 'clean' => [
            'kind' => $kind, 'title' => $title, 'form' => $form, 'subject' => $subject,
            'instructions' => trim(str_replace("\r\n", "\n", (string) ($in['instructions'] ?? ''))) ?: null,
            'opens_at' => $opens, 'closes_at' => $closes,
            'duration_min' => in_array($kind, self::TIMED, true) ? $duration : null,
        ]];
    }

    /** "2026-12-01T08:00" (datetime-local) -> "2026-12-01 08:00:00", or null. */
    private static function dateTime(string $s): ?string
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim($s)) ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($s));

        return $d === false ? null : $d->format('Y-m-d H:i:00');
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->assessments->all();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->assessments->find($id);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: ?int}
     */
    public function save(?int $id, array $input, int $tutorId): array
    {
        $v = self::validateDetails($input);
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'id' => null];
        }
        if ($id === null) {
            return ['errors' => [], 'id' => $this->assessments->create($v['clean'], $tutorId)];
        }
        $current = $this->assessments->find($id);
        if ($current !== null && ((int) $current['form'] !== $v['clean']['form'] || $current['subject'] !== $v['clean']['subject'])
            && $this->assessments->questions($id) !== []) {
            return ['errors' => ['form' => 'Remove the questions first to change the form or subject.'], 'id' => null];
        }
        $this->assessments->update($id, $v['clean']);

        return ['errors' => [], 'id' => $id];
    }

    /** @return list<array<string, mixed>> */
    public function questionsIn(int $id): array
    {
        return $this->assessments->questions($id);
    }

    public function addQuestion(int $assessmentId, int $questionId): string
    {
        $a = $this->assessments->find($assessmentId);
        $q = $this->questions->find($questionId);
        if ($a === null || $q === null) {
            return 'Question not found.';
        }
        if ((int) $q['form'] !== (int) $a['form'] || $q['subject'] !== $a['subject']) {
            return 'Only questions from the same form and subject can be added.';
        }
        if ($a['status'] !== 'draft' && $this->submissions->forAssessment($assessmentId) !== []) {
            return 'Students have already started. Questions can no longer change.';
        }
        $this->assessments->addQuestion($assessmentId, $questionId);

        return '';
    }

    public function removeQuestion(int $assessmentId, int $questionId): string
    {
        if ($this->submissions->forAssessment($assessmentId) !== []) {
            return 'Students have already started. Questions can no longer change.';
        }
        $this->assessments->removeQuestion($assessmentId, $questionId);

        return '';
    }

    public function moveQuestion(int $assessmentId, int $questionId, int $direction): void
    {
        $this->assessments->moveQuestion($assessmentId, $questionId, $direction < 0 ? -1 : 1);
    }

    /** Publish and tell the students who can take it (their school has taught every topic in it). */
    public function publish(int $id): string
    {
        $a = $this->assessments->find($id);
        if ($a === null) {
            return 'Not found.';
        }
        if ($this->assessments->questions($id) === []) {
            return 'Add at least one question before publishing.';
        }
        $this->assessments->setStatus($id, 'published');
        $topics = $this->assessments->topicIds($id);
        $eligible = [];
        foreach ($this->access->allStudents() as $s) {
            if ((int) $s['form'] !== (int) $a['form'] || !in_array($a['subject'], Subjects::forTrack((string) $s['track']), true)) {
                continue;
            }
            $decide = $this->access->forStudent((int) $s['user_id']);
            if ($decide !== null && self::allOpen($decide, $topics)) {
                $eligible[] = (int) $s['user_id'];
            }
        }
        $this->notifications->notify($eligible, 'assessment_published', 'New ' . strtolower(self::KINDS[$a['kind']] ?? 'assessment') . ': ' . $a['title'],
            $a['closes_at'] !== null ? 'Closes ' . date('j M, H:i', (int) strtotime((string) $a['closes_at'])) . '.' : null, '/assessments/' . $id);

        return '';
    }

    /** @param \Closure(int): array{open: bool} $decide @param list<int> $topics */
    public static function allOpen(\Closure $decide, array $topics): bool
    {
        foreach ($topics as $t) {
            if (!$decide($t)['open']) {
                return false;
            }
        }

        return true;
    }

    public function close(int $id): void
    {
        $this->assessments->setStatus($id, 'closed');
    }

    public function reopen(int $id): void
    {
        $this->assessments->setStatus($id, 'published');
    }

    public function delete(int $id): string
    {
        if ($this->submissions->forAssessment($id) !== []) {
            return 'Students have answered it. Close it instead of deleting.';
        }
        $this->assessments->delete($id);

        return '';
    }

    /** Release marks to students. Every submitted answer must be marked first. */
    public function releaseResults(int $id): string
    {
        $a = $this->assessments->find($id);
        if ($a === null) {
            return 'Not found.';
        }
        foreach ($this->submissions->forAssessment($id) as $s) {
            if ($s['status'] === 'submitted') {
                return 'Mark every submission first (' . $s['full_name'] . ' is waiting).';
            }
        }
        $this->assessments->releaseResults($id);
        $this->notifications->notify($this->submissions->submitters($id), 'results_released',
            'Results ready: ' . $a['title'], 'See your marks and the tutor’s comments.', '/assessments/' . $id . '/result');

        return '';
    }

    /* ---------- Marking ---------- */

    /** @return list<array<string, mixed>> every submission waiting for marking, across exams and notes */
    public function toMark(): array
    {
        return $this->submissions->toMark();
    }

    /** @return list<array<string, mixed>> */
    public function submissions(int $assessmentId): array
    {
        return $this->submissions->forAssessment($assessmentId);
    }

    /** @return array{submission: array<string, mixed>, questions: list<array<string, mixed>>, answers: array<int, array<string, mixed>>}|null */
    public function markingView(int $submissionId): ?array
    {
        $s = $this->submissions->find($submissionId);
        if ($s === null) {
            return null;
        }

        return [
            'submission' => $s,
            'questions' => $this->assessments->questions((int) $s['assessment_id']),
            'answers' => $this->submissions->answers($submissionId),
        ];
    }

    /**
     * Save the tutor's marks. A blank score keeps the automatic one (where there is one).
     * @param array<string, mixed> $scores question id => score typed by the tutor
     * @param array<string, mixed> $feedback did_well|off_track|next_step => question id => note
     * @return array<string, string> errors (question id => message)
     */
    public function mark(int $submissionId, array $scores, array $feedback, string $overall, int $tutorId): array
    {
        $view = $this->markingView($submissionId);
        if ($view === null || $view['submission']['status'] === 'in_progress') {
            return ['submission' => 'This attempt has not been submitted yet.'];
        }
        $errors = [];
        $marks = [];
        $total = 0.0;
        foreach ($view['questions'] as $q) {
            $qid = (int) $q['id'];
            $max = (float) $q['max_marks'];
            $typed = trim((string) ($scores[$qid] ?? ''));
            $auto = $view['answers'][$qid]['auto_score'] ?? null;
            if ($typed === '') {
                if ($auto === null) {
                    $errors[(string) $qid] = 'Give a mark for this answer.';
                    continue;
                }
                $score = null;
                $total += (float) $auto;
            } elseif (!is_numeric($typed) || (float) $typed < 0 || (float) $typed > $max) {
                $errors[(string) $qid] = 'Give a mark from 0 to ' . self::fmt($max) . '.';
                continue;
            } else {
                $score = round((float) $typed, 2);
                $total += $score;
            }
            $note = static fn (string $key): string => mb_substr(trim((string) ($feedback[$key][$qid] ?? '')), 0, 2000);
            $marks[$qid] = ['score' => $score, 'did_well' => $note('did_well'), 'off_track' => $note('off_track'), 'next_step' => $note('next_step')];
        }
        if ($errors !== []) {
            return $errors;
        }
        $this->submissions->mark($submissionId, $marks, round($total, 2), mb_substr(trim($overall), 0, 3000), $tutorId);

        return [];
    }

    public static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
