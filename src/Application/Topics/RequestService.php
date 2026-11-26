<?php

declare(strict_types=1);

namespace SmartCircle\Application\Topics;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\AccessRepository;
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
        private readonly ?AccessService $access = null,
        private readonly ?AccessRepository $accessRepo = null,
    ) {
    }

    /**
     * Validate a student's request form. Pure: used by request() and by the tests.
     * When the topic is open to the student it is a normal request; when it is locked
     * (not taught at their school yet) it is an early-access request and needs a reason.
     * @param array<string, mixed> $input struggle_type, details, exception_reason
     * @return array{errors: array<string, string>, struggle: string, details: string, exception: bool, reason: ?string}
     */
    public static function validate(array $input, bool $isFirst, bool $isOpen): array
    {
        $struggle = (string) ($input['struggle_type'] ?? '');
        $details = trim(str_replace("\r\n", "\n", (string) ($input['details'] ?? '')));
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
        if (!$isOpen && mb_strlen($reason) < 10) {
            $errors['exception_reason'] = 'Explain why you need it before your school teaches it (at least 10 characters).';
        }

        return [
            'errors' => $errors,
            'struggle' => $struggle,
            'details' => mb_substr($details, 0, 1000),
            'exception' => !$isOpen,
            'reason' => $isOpen ? null : mb_substr($reason, 0, 1000),
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
        $isOpen = $this->access === null || $this->access->check($userId, 'student', $topic)['open'];
        $v = self::validate($input, $existing === null, $isOpen);
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'outcome' => null];
        }
        if ($v['exception']) {
            $this->accessRepo?->requestException($userId, $topicId, (string) $v['reason']);
            $this->notifications->notifyTutors('early_access', 'Early access asked: ' . $topic['title'],
                'A student needs this before their school teaches it.', '/studio/checks');
        }

        $title = (string) $topic['title'];
        $link = '/studio/requests/';

        if ($existing === null) {
            $id = $this->requests->create($topicId, $userId, $v['struggle'], $v['details'], $v['exception'], $v['reason']);
            $this->notifications->notifyTutors(
                'request_new',
                'New request: ' . $title,
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

        // A student for whom the topic IS taught turns an early-access request into a normal one.
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
        $readiness = $this->access?->readiness() ?? [];
        $deciders = $this->access?->allDeciders() ?? [];
        foreach ($rows as &$row) {
            $row['struggles'] = self::sortBreakdown($breakdown[(int) $row['id']] ?? []);
            $row['readiness'] = $readiness[(int) $row['topic_id']] ?? ['ready' => 0, 'eligible' => 0];
            $taught = 0;
            foreach ($this->requests->participants((int) $row['id']) as $uid) {
                $d = isset($deciders[$uid]) ? $deciders[$uid]((int) $row['topic_id']) : null;
                if ($d !== null && AccessService::taught($d)) {
                    $taught++;
                }
            }
            $row['taught_votes'] = $taught;
        }
        unset($row);

        return self::rank($rows);
    }

    /**
     * Topics most students' schools have taught but that have no lesson yet: good to record
     * before anyone asks. At least half of the students who study it must have it taught.
     * @param array{form?: int, subject?: string} $filter
     * @return list<array<string, mixed>>
     */
    public function readyToRecord(array $filter, int $limit = 8): array
    {
        if ($this->access === null) {
            return [];
        }
        $readiness = $this->access->readiness();
        $rows = [];
        foreach ($this->topics->mainTopicsWithoutLessons() as $t) {
            if ((!empty($filter['form']) && (int) $t['form'] !== (int) $filter['form'])
                || (!empty($filter['subject']) && $t['subject'] !== $filter['subject'])) {
                continue;
            }
            $r = $readiness[(int) $t['id']] ?? ['ready' => 0, 'eligible' => 0];
            if ($r['eligible'] > 0 && $r['ready'] * 2 >= $r['eligible']) {
                $rows[] = $t + ['readiness' => $r];
            }
        }
        usort($rows, static fn (array $a, array $b): int =>
            [$b['readiness']['ready'] / $b['readiness']['eligible'], $b['readiness']['ready']]
            <=> [$a['readiness']['ready'] / $a['readiness']['eligible'], $a['readiness']['ready']]);

        return array_slice($rows, 0, $limit);
    }

    /**
     * Honest priority: first the students whose school HAS taught the topic, then how many of
     * all students' schools have taught it (readiness), then all voices, oldest first.
     * @param list<array<string, mixed>> $rows each with taught_votes, readiness, votes, created_at
     * @return list<array<string, mixed>>
     */
    public static function rank(array $rows): array
    {
        $ratio = static fn (array $r): float => $r['readiness']['eligible'] > 0 ? $r['readiness']['ready'] / $r['readiness']['eligible'] : 0.0;
        usort($rows, static fn (array $a, array $b): int =>
            [(int) $b['taught_votes'], $ratio($b), (int) $b['votes'], (string) $a['created_at']]
            <=> [(int) $a['taught_votes'], $ratio($a), (int) $a['votes'], (string) $b['created_at']]);

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
        $deciders = $this->access?->allDeciders() ?? [];
        foreach ($request['voices'] as &$voice) {
            $voice['access'] = isset($deciders[$voice['user_id']]) ? $deciders[$voice['user_id']]((int) $request['topic_id']) : null;
        }
        unset($voice);

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

    /**
     * A lesson on a topic was published: every open/planned request on that topic
     * (or its parent/subtopics) becomes "Lesson ready" and its students are told.
     */
    public function lessonPublished(int $lessonId, int $topicId, string $lessonTitle): int
    {
        $ids = $this->requests->openForTopicTree($topicId);
        $deciders = $this->access?->allDeciders() ?? [];
        foreach ($ids as $id) {
            $this->requests->setStatus($id, 'recorded', $lessonId);
            $open = $waiting = [];
            foreach ($this->requests->participants($id) as $uid) {
                $canWatch = $this->access === null || (isset($deciders[$uid]) && $deciders[$uid]($topicId)['open']);
                $canWatch ? $open[] = $uid : $waiting[] = $uid;
            }
            $this->notifications->notify($open, 'request_recorded',
                'Lesson ready: ' . $lessonTitle, 'The lesson you asked for is ready to watch.', '/lessons/' . $lessonId);
            $this->notifications->notify($waiting, 'request_recorded',
                'Lesson recorded: ' . $lessonTitle, 'It opens for you when your school has taught this topic.', '/topics/' . $topicId);
        }

        return count($ids);
    }
}
