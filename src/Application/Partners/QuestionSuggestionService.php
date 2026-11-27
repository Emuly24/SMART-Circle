<?php

declare(strict_types=1);

namespace SmartCircle\Application\Partners;

use SmartCircle\Application\Assessments\QuestionForm;
use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\QuestionRepository;
use SmartCircle\Data\Repositories\QuestionSuggestionRepository;
use SmartCircle\Data\Repositories\ReviewRepository;
use SmartCircle\Data\Repositories\TopicRepository;
use SmartCircle\Support\Subjects;

/**
 * Question Contributor: partner teachers suggest questions in the subjects they teach. The tutor accepts a
 * suggestion into the question bank (credited to the teacher) or declines it with a short, kind reason.
 */
final class QuestionSuggestionService
{
    public function __construct(
        private readonly QuestionSuggestionRepository $suggestions,
        private readonly QuestionRepository $questions,
        private readonly ReviewRepository $partners,
        private readonly TopicRepository $topics,
        private readonly NotificationService $notifications,
    ) {
    }

    /** @return list<string> the teacher's subjects (empty if not an active partner) */
    public function subjectsOf(int $partnerId): array
    {
        $p = $this->partners->reviewer($partnerId);

        return $p === null ? [] : array_values(array_filter(explode(',', (string) $p['subjects'])));
    }

    /**
     * @param array<string, mixed> $input as the question form sends it
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public function suggest(int $partnerId, array $input): array
    {
        $v = QuestionForm::validate($input);
        $errors = $v['errors'];
        $q = $v['clean'];
        if (!isset($errors['subject']) && !in_array($q['subject'], $this->subjectsOf($partnerId), true)) {
            $errors['subject'] = 'Choose one of the subjects you teach.';
        }
        if ($q['topic_id'] !== null) {
            $t = $this->topics->find((int) $q['topic_id']);
            if ($t === null || (int) $t['form'] !== (int) $q['form'] || $t['subject'] !== $q['subject']) {
                $errors['topic_id'] = 'Choose a topic from the same form and subject.';
            }
        }
        if ($errors === []) {
            $this->suggestions->add($partnerId, $q);
            $name = (string) ($this->partners->reviewer($partnerId)['full_name'] ?? 'A partner teacher');
            $this->notifications->notifyTutors('question', 'Question suggested by ' . $name,
                'Form ' . $q['form'] . ' ' . (Subjects::NAMES[$q['subject']] ?? '') . ': ' . mb_substr((string) $q['prompt'], 0, 120), '/studio/question-suggestions');
        }

        return ['errors' => $errors, 'clean' => $q];
    }

    /** @return list<array<string, mixed>> */
    public function mine(int $partnerId): array
    {
        return $this->suggestions->forPartner($partnerId);
    }

    /** @return array{pending: list<array<string, mixed>>, decided: list<array<string, mixed>>} */
    public function forTutor(): array
    {
        return ['pending' => self::decode($this->suggestions->byStatus('pending')),
            'decided' => array_merge(self::decode($this->suggestions->byStatus('accepted')), self::decode($this->suggestions->byStatus('declined')))];
    }

    /** @return string error ('' when done) */
    public function accept(int $id): string
    {
        $s = $this->suggestions->find($id);
        if ($s === null || $s['status'] !== 'pending') {
            return 'This suggestion has already been decided.';
        }
        $questionId = $this->questions->create([
            'form' => (int) $s['form'], 'subject' => (string) $s['subject'], 'topic_id' => $s['topic_id'] !== null ? (int) $s['topic_id'] : null,
            'type' => (string) $s['type'], 'prompt' => (string) $s['prompt'], 'data' => json_decode((string) $s['data'], true) ?: [],
            'marks' => (int) $s['marks'], 'is_application' => false,
        ], (int) $s['partner_id']);
        $this->suggestions->decide($id, 'accepted', null, $questionId);
        if ($s['partner_id'] !== null) {
            $this->notifications->notify([(int) $s['partner_id']], 'question', 'Your question was added to the question bank. Thank you!',
                mb_substr((string) $s['prompt'], 0, 160), '/partner/questions');
        }

        return '';
    }

    /** @return string error ('' when done) */
    public function decline(int $id, string $note): string
    {
        $s = $this->suggestions->find($id);
        if ($s === null || $s['status'] !== 'pending') {
            return 'This suggestion has already been decided.';
        }
        $note = trim($note);
        if (mb_strlen($note) < 5) {
            return 'Please give the teacher a short reason, so their next suggestion fits.';
        }
        $this->suggestions->decide($id, 'declined', mb_substr($note, 0, 500), null);
        if ($s['partner_id'] !== null) {
            $this->notifications->notify([(int) $s['partner_id']], 'question', 'About your suggested question', $note, '/partner/questions');
        }

        return '';
    }

    public function pendingCount(): int
    {
        return $this->suggestions->countPending();
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private static function decode(array $rows): array
    {
        return array_map(static fn (array $r): array => ['data' => json_decode((string) $r['data'], true) ?: []] + $r, $rows);
    }
}
