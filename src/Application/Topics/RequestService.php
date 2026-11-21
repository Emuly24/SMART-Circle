<?php

declare(strict_types=1);

namespace SmartCircle\Application\Topics;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\CoverageRepository;
use SmartCircle\Data\Repositories\TopicRepository;
use SmartCircle\Data\Repositories\TopicRequestRepository;
use SmartCircle\Support\Subjects;

/**
 * Topic requests.
 *  - One request per topic; classmates who are stuck on the same topic add a "me too".
 *  - Each voice says HOW they are stuck (struggle type), so the tutor can aim the lesson.
 *  - Normal requests are for topics the student's school has covered. A topic not yet
 *    covered can still be asked for as an exception with a reason; the tutor approves it.
 *  - The tutor's list is ranked: pending exceptions first, then by number of voices.
 */
final class RequestService
{
    public const STRUGGLES = [
        'idea' => 'I don’t understand the idea',
        'calculations' => 'I get stuck in the calculations / working',
        'real_life' => 'I don’t see where it is used in real life',
        'exam' => 'I can’t answer exam questions on it',
        'practical' => 'I don’t understand the practical / experiment',
    ];

    public const STRUGGLES_SHORT = [
        'idea' => 'Idea',
        'calculations' => 'Working',
        'real_life' => 'Real life',
        'exam' => 'Exam questions',
        'practical' => 'Practical',
    ];

    public const STATUSES = [
        'open' => 'Waiting',
        'planned' => 'Planned',
        'recorded' => 'Lesson ready',
        'closed' => 'Closed',
    ];

    public function __construct(
        private readonly TopicRepository $topics,
        private readonly TopicRequestRepository $requests,
        private readonly NotificationService $notifications,
        private readonly ?CoverageRepository $coverage = null,
    ) {
    }

    /**
     * Validate a student's request form. Pure: used by request() and by the tests.
     * @param array<string, mixed> $input struggle_type, details, covered ('yes'|'no'), exception_reason
     * @return array{errors: array<string, string>, struggle: string, details: string, exception: bool, reason: ?string}
     */
    public static function validate(array $input, bool $isFirst, ?string $schoolCoverage): array
    {
        $struggle = (string) ($input['struggle_type'] ?? '');
        $details = trim(str_replace("\r\n", "\n", (string) ($input['details'] ?? '')));
        $covered = (string) ($input['covered'] ?? '');
        $reason = trim((string) ($input['exception_reason'] ?? ''));
        $errors = [];

        if (!isset(self::STRUGGLES[$struggle])) {
            $errors['struggle_type'] = 'Choose how you are stuck.';
        }
        if ($isFirst && mb_strlen($details) < 10) {
            $errors['details'] = 'Say where exactly you get lost (at least 10 characters).';
        }
        if (mb_strlen($details) > 1000) {
            $errors['details'] = 'Keep it under 1000 characters.';
        }

        // The school's own report wins; otherwise the student tells us.
        if (in_array($schoolCoverage, ['covered', 'covering'], true)) {
            $exception = false;
        } elseif ($covered === 'yes') {
            $exception = false;
        } elseif ($covered === 'no') {
            $exception = true;
            if (mb_strlen($reason) < 10) {
                $errors['exception_reason'] = 'Explain why you need it before your school teaches it (at least 10 characters).';
            }
        } else {
            $exception = false;
            $errors['covered'] = 'Tell us whether your school has taught this topic yet.';
        }

        return [
            'errors' => $errors,
            'struggle' => $struggle,
            'details' => mb_substr($details, 0, 1000),
            'exception' => $exception,
            'reason' => $exception ? mb_substr($reason, 0, 1000) : null,
        ];
    }

    /**
     * A student asks for a topic (or adds their voice to the existing request).
     * @param array<string, mixed> $profile student profile (form, track, school_id)
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, outcome: ?string} outcome: requested | joined | updated | already
     */
    public function request(int $topicId, int $userId, array $profile, array $input): array
    {
        $topic = $this->topics->find($topicId);
        if ($topic === null || !(bool) $topic['is_active']
            || !TopicService::mayBrowse($profile, (int) $topic['form'], (string) $topic['subject'])) {
            return ['errors' => ['topic' => 'This topic is not part of your subjects.'], 'outcome' => null];
        }

        $existing = $this->requests->forTopic($topicId);
        $coverage = !empty($profile['school_id']) ? $this->topics->coverage((int) $profile['school_id'], $topicId) : null;
        $v = self::validate($input, $existing === null, $coverage);
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'outcome' => null];
        }

        $title = (string) $topic['title'];
        $link = '/studio/requests/';

        if ($existing === null) {
            $id = $this->requests->create($topicId, $userId, $v['struggle'], $v['details'], $v['exception'], $v['reason']);
            $this->notifications->notifyTutors(
                $v['exception'] ? 'request_exception' : 'request_new',
                ($v['exception'] ? 'Exception request: ' : 'New request: ') . $title,
                'Form ' . $topic['form'] . ' ' . Subjects::NAMES[$topic['subject']] . ' · ' . self::STRUGGLES_SHORT[$v['struggle']],
                $link . $id
            );

            return ['errors' => [], 'outcome' => 'requested'];
        }

        $requestId = (int) $existing['id'];
        if ((int) $existing['user_id'] === $userId) {
            return ['errors' => [], 'outcome' => 'already'];
        }
        $already = $this->requests->hasJoined($requestId, $userId);
        $this->requests->vote($requestId, $userId, $v['struggle'], $v['details'] === '' ? null : $v['details']);

        // A classmate whose school HAS covered it turns an exception into a normal request.
        if ((bool) $existing['is_exception'] && !$v['exception']) {
            $this->requests->clearException($requestId);
        }
        // Still stuck after the lesson, or the tutor closed it: reopen it.
        if (in_array($existing['status'], ['recorded', 'closed'], true) && !$already) {
            $this->requests->setStatus($requestId, 'open');
        }

        return ['errors' => [], 'outcome' => $already ? 'updated' : 'joined'];
    }

    /**
     * The tutor's ranked list, with the struggle breakdown for each request.
     * @param array{form?: int, subject?: string, status?: string} $filter
     * @return list<array<string, mixed>>
     */
    public function ranked(array $filter): array
    {
        $rows = $this->requests->ranked($filter);
        $breakdown = $this->requests->struggleBreakdown();
        foreach ($rows as &$row) {
            $row['struggles'] = self::sortBreakdown($breakdown[(int) $row['id']] ?? []);
        }

        return $rows;
    }

    /**
     * @param array<string, int> $counts
     * @return array<string, int> most common first
     */
    public static function sortBreakdown(array $counts): array
    {
        uksort($counts, static fn (string $a, string $b): int => [$counts[$b], array_search($a, array_keys(self::STRUGGLES), true)]
            <=> [$counts[$a], array_search($b, array_keys(self::STRUGGLES), true)]);

        return $counts;
    }

    /** @return array<string, mixed>|null request with topic and everyone's voices */
    public function detail(int $id): ?array
    {
        $request = $this->requests->find($id);
        if ($request === null) {
            return null;
        }
        $request['voices'] = $this->requests->voices($id);
        $request['struggles'] = self::sortBreakdown(array_count_values(array_column($request['voices'], 'struggle_type')));
        $request['schools'] = $this->coverage?->forTopic((int) $request['topic_id']) ?? [];

        return $request;
    }

    /** @return array<string, mixed>|null the request on a topic, with whether this user has joined */
    public function forTopic(int $topicId, int $userId): ?array
    {
        $request = $this->requests->forTopic($topicId);
        if ($request === null) {
            return null;
        }
        $request['joined'] = $this->requests->hasJoined((int) $request['id'], $userId);
        $request['voice_count'] = count($this->requests->participants((int) $request['id']));

        return $request;
    }

    /** Tutor: planned / closed / open. 'recorded' happens when a lesson is published. */
    public function setStatus(int $id, string $status): bool
    {
        $request = $this->requests->find($id);
        if ($request === null || !in_array($status, ['open', 'planned', 'closed'], true) || $request['status'] === $status) {
            return false;
        }
        if ($request['exception_status'] === 'pending' && $status === 'planned') {
            // Planning an exception is approving it.
            $this->requests->setException($id, 'approved');
        }
        $this->requests->setStatus($id, $status);

        $link = '/topics/' . $request['topic_id'];
        if ($status === 'planned') {
            $this->notifications->notify($this->requests->participants($id), 'request_planned',
                'Lesson planned: ' . $request['topic_title'], 'The tutor is preparing a lesson on the topic you asked for.', $link);
        } elseif ($status === 'closed') {
            $this->notifications->notify($this->requests->participants($id), 'request_closed',
                'Request closed: ' . $request['topic_title'], null, $link);
        }

        return true;
    }

    /** Tutor: approve or decline an exception (a topic the student's school has not covered). */
    public function decideException(int $id, bool $approve): bool
    {
        $request = $this->requests->find($id);
        if ($request === null || !(bool) $request['is_exception']) {
            return false;
        }
        $this->requests->setException($id, $approve ? 'approved' : 'declined');
        if (!$approve) {
            $this->requests->setStatus($id, 'closed');
        }
        $this->notifications->notify(
            $this->requests->participants($id),
            'request_exception',
            ($approve ? 'Exception approved: ' : 'Exception declined: ') . $request['topic_title'],
            $approve ? 'The tutor will teach this topic before your school reaches it.'
                : 'Ask again once your school has taught this topic.',
            '/topics/' . $request['topic_id']
        );

        return true;
    }

    /**
     * A lesson on a topic was published: every open/planned request on that topic
     * (or its parent/subtopics) becomes "Lesson ready" and its students are told.
     */
    public function lessonPublished(int $lessonId, int $topicId, string $lessonTitle): int
    {
        $ids = $this->requests->openForTopicTree($topicId);
        foreach ($ids as $id) {
            $this->requests->setStatus($id, 'recorded', $lessonId);
            $this->notifications->notify($this->requests->participants($id), 'request_recorded',
                'Lesson ready: ' . $lessonTitle, 'The lesson you asked for is ready to watch.', '/lessons/' . $lessonId);
        }

        return count($ids);
    }
}
