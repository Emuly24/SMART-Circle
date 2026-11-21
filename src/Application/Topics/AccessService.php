<?php

declare(strict_types=1);

namespace SmartCircle\Application\Topics;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\AccessRepository;
use SmartCircle\Data\Repositories\CoverageRepository;
use SmartCircle\Data\Repositories\TopicRepository;
use SmartCircle\Support\Subjects;

/**
 * The honesty rule: SMART Circle reinforces what a student's OWN school has taught.
 * A topic (and its lessons, requests and later its exercises) is open to a student when:
 *   - their school reports it taught or being taught                       -> basis "school"
 *   - they report "my class has covered it" and no teacher reports that
 *     subject for their school yet (open at once, checked by the tutor)    -> basis "self"
 *     ... or the tutor confirmed their report                              -> basis "self"
 *   - the tutor approved an early-access request                           -> basis "exception"
 * "My class has not reached it" always wins over the school's report (only ever locks).
 * Parent topics count for their subtopics.
 */
final class AccessService
{
    public const STATES = [
        'school' => 'Taught at your school',
        'self_confirmed' => 'Your class has covered it (confirmed)',
        'self_unverified' => 'Your class has covered it (you told us)',
        'exception' => 'Opened early by the tutor',
        'class_not_yet' => 'Your class has not reached it yet',
        'awaiting_check' => 'Waiting for the tutor to check',
        'exception_pending' => 'Early access requested',
        'not_taught' => 'Not taught at your school yet',
        'unknown' => 'Not taught at your school yet',
    ];

    /** Short labels for the tutor's screens. */
    public const TUTOR_STATES = [
        'school' => 'Taught at school',
        'self_confirmed' => 'Class covered (confirmed)',
        'self_unverified' => 'Class covered (unchecked)',
        'exception' => 'Opened early',
        'class_not_yet' => 'Class not there yet',
        'awaiting_check' => 'Needs your check',
        'exception_pending' => 'Asked early',
        'not_taught' => 'Not taught yet',
        'unknown' => 'Not taught yet',
    ];

    /** @var array<int, int|null>|null */
    private ?array $parents = null;

    public function __construct(
        private readonly AccessRepository $access,
        private readonly TopicRepository $topics,
        private readonly CoverageRepository $coverage,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * The rule, as a pure function (unit tested).
     * @param array<int, string> $schoolCoverage topic => covered | covering | next
     * @param array<int, array{status: string, review: ?string}> $reports the student's own reports
     * @param array<int, string> $exceptions topic => pending | approved | declined
     * @return array{open: bool, basis: ?string, state: string}
     */
    public static function decide(int $topicId, ?int $parentId, array $schoolCoverage, array $reports, array $exceptions, bool $subjectReported): array
    {
        $ids = $parentId === null ? [$topicId] : [$topicId, $parentId];
        $first = static function (array $map) use ($ids): mixed {
            foreach ($ids as $id) {
                if (isset($map[$id])) {
                    return $map[$id];
                }
            }

            return null;
        };
        $report = $first($reports);
        if ($report !== null && ($report['review'] ?? null) === 'rejected') {
            $report = null;
        }
        $exception = $first($exceptions);
        $open = static fn (string $basis, string $state): array => ['open' => true, 'basis' => $basis, 'state' => $state];
        $locked = static fn (string $state): array => ['open' => false, 'basis' => null, 'state' => $state];

        if ($report !== null && $report['status'] === 'not_yet') {
            return $exception === 'approved' ? $open('exception', 'exception') : $locked('class_not_yet');
        }
        foreach ($ids as $id) {
            if (in_array($schoolCoverage[$id] ?? '', ['covered', 'covering'], true)) {
                return $open('school', 'school');
            }
        }
        if ($exception === 'approved') {
            return $open('exception', 'exception');
        }
        if ($report !== null && $report['status'] === 'covered') {
            if (($report['review'] ?? null) === 'confirmed') {
                return $open('self', 'self_confirmed');
            }
            if (!$subjectReported) {
                return $open('self', 'self_unverified');
            }

            return $locked('awaiting_check');
        }
        if ($exception === 'pending') {
            return $locked('exception_pending');
        }

        return $locked($subjectReported ? 'not_taught' : 'unknown');
    }

    /**
     * One student's view of the catalogue.
     * @return \Closure(int): array{open: bool, basis: ?string, state: string}|null  topic id => decision; null if not a student
     */
    public function forStudent(int $userId): ?\Closure
    {
        $students = $this->access->students($userId);

        return $students === [] ? null : $this->deciders($students)[$userId];
    }

    /** @return array<int, \Closure(int): array{open: bool, basis: ?string, state: string}> every active student */
    public function allDeciders(): array
    {
        return $this->deciders($this->access->students());
    }

    /**
     * Decision for whoever is asking about one topic. The tutor sees everything.
     * @param array<string, mixed> $topic
     * @return array{open: bool, basis: ?string, state: string}
     */
    public function check(?int $userId, ?string $role, array $topic): array
    {
        if ($role === 'tutor') {
            return ['open' => true, 'basis' => 'tutor', 'state' => 'school'];
        }
        $decide = $userId !== null ? $this->forStudent($userId) : null;

        return $decide === null ? ['open' => false, 'basis' => null, 'state' => 'unknown'] : $decide((int) $topic['id']);
    }

    /**
     * Readiness: for every topic, how many students who study it have it open
     * because their school (or their own class) has taught it. Exceptions do not count.
     * @return array<int, array{ready: int, eligible: int}>
     */
    public function readiness(): array
    {
        $students = $this->access->students();
        $deciders = $this->deciders($students);
        $out = [];
        foreach ($this->topics->allActive() as $t) {
            $id = (int) $t['id'];
            $ready = $eligible = 0;
            foreach ($students as $s) {
                if ((int) $s['form'] !== (int) $t['form'] || !in_array($t['subject'], Subjects::forTrack((string) $s['track']), true)) {
                    continue;
                }
                $eligible++;
                $d = $deciders[(int) $s['user_id']]($id);
                if ($d['open'] && $d['basis'] !== 'exception') {
                    $ready++;
                }
            }
            $out[$id] = ['ready' => $ready, 'eligible' => $eligible];
        }

        return $out;
    }

    /**
     * Snapshot of what is open, to find what a change unlocked.
     * @param list<int> $topicIds
     * @return array<string, true> "user:topic" => open
     */
    public function openSet(?int $schoolId, array $topicIds): array
    {
        $students = array_values(array_filter($this->access->students(), static fn (array $s): bool => $schoolId === null || (int) $s['school_id'] === $schoolId));
        $deciders = $this->deciders($students);
        $set = [];
        foreach ($students as $s) {
            foreach ($topicIds as $t) {
                if ($deciders[(int) $s['user_id']]($t)['open']) {
                    $set[$s['user_id'] . ':' . $t] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Tell students about topics that just opened for them and already have lessons.
     * @param array<string, true> $before @param array<string, true> $after from openSet()
     */
    public function notifyUnlocked(array $before, array $after, string $why): int
    {
        $sent = 0;
        foreach (array_keys(array_diff_key($after, $before)) as $key) {
            [$userId, $topicId] = array_map('intval', explode(':', $key));
            $topic = $this->topics->find($topicId);
            $student = $this->access->students($userId)[0] ?? null;
            if ($topic === null || $student === null
                || !in_array($topic['subject'], Subjects::forTrack((string) $student['track']), true) || (int) $topic['form'] !== (int) $student['form']) {
                continue;
            }
            $lessons = $this->access->lessonsForTopicTree($topicId);
            $this->notifications->notify(
                [$userId],
                'topic_unlocked',
                $why . ': ' . $topic['title'],
                $lessons === [] ? 'You can now ask for help on this topic.'
                    : (count($lessons) === 1 ? 'A lesson is ready for you.' : count($lessons) . ' lessons are ready for you.'),
                '/topics/' . $topicId
            );
            $sent++;
        }

        return $sent;
    }

    /* ---------- Students' own reports ---------- */

    /**
     * "My class has covered it" (covered), "my class has not reached it" (not_yet) or undo (clear).
     * @param array<string, mixed> $profile
     * @return string error ('' when saved)
     */
    public function report(int $userId, array $profile, int $topicId, string $status): string
    {
        $topic = $this->topics->find($topicId);
        if ($topic === null || !TopicService::mayBrowse($profile, (int) $topic['form'], (string) $topic['subject'])) {
            return 'This topic is not part of your subjects.';
        }
        if ($status === 'clear') {
            $this->access->deleteReport($userId, $topicId);
            return '';
        }
        if (!in_array($status, ['covered', 'not_yet'], true)) {
            return 'Choose an answer.';
        }
        $this->access->saveReport($userId, $topicId, $status);
        if ($status === 'covered' && $this->check($userId, 'student', $topic)['state'] === 'awaiting_check') {
            $this->notifications->notifyTutors('coverage_check', 'Coverage check: ' . $topic['title'],
                'A student says their class has covered this, but their school has not reported it.', '/studio/checks');
        }

        return '';
    }

    /* ---------- Tutor decisions ---------- */

    public function decideException(int $userId, int $topicId, bool $approve, int $tutorId): void
    {
        $key = [$userId . ':' . $topicId => true];
        $before = array_intersect_key($this->openSet(null, [$topicId]), $key);
        $this->access->decideException($userId, $topicId, $approve ? 'approved' : 'declined', $tutorId);
        if ($approve) {
            $this->notifyUnlocked($before, array_intersect_key($this->openSet(null, [$topicId]), $key), 'Opened early for you');
        } else {
            $topic = $this->topics->find($topicId);
            $this->notifications->notify([$userId], 'exception_declined', 'Early access declined: ' . ($topic['title'] ?? ''),
                'It will open when your school teaches it.', '/topics/' . $topicId);
        }
    }

    /**
     * Check a student's "my class has covered it". Confirming can also record it for the whole school.
     */
    public function reviewReport(int $userId, int $topicId, bool $confirm, bool $wholeSchool, int $tutorId): void
    {
        $student = $this->access->students($userId)[0] ?? null;
        $schoolId = $student !== null && $student['school_id'] !== null ? (int) $student['school_id'] : null;
        $before = $this->openSet($wholeSchool ? $schoolId : null, [$topicId]);
        $this->access->reviewReport($userId, $topicId, $confirm ? 'confirmed' : 'rejected', $tutorId);
        if ($confirm && $wholeSchool && $schoolId !== null) {
            $this->coverage->save($schoolId, [$topicId => 'covered'], $tutorId, 'tutor');
        }
        $after = $this->openSet($wholeSchool ? $schoolId : null, [$topicId]);
        $this->notifyUnlocked($before, $after, 'Your school has covered');
        if (!$confirm) {
            $topic = $this->topics->find($topicId);
            $this->notifications->notify([$userId], 'coverage_rejected', 'Not confirmed: ' . ($topic['title'] ?? ''),
                'Your school has not taught this yet. It will open when it does.', '/topics/' . $topicId);
        }
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function checks(): array
    {
        $reports = $this->access->uncheckedReports();
        $reported = $this->access->reportedSubjects(array_values(array_unique(array_filter(array_map(
            static fn (array $r): int => (int) $r['school_id'],
            $reports
        )))));
        foreach ($reports as &$r) {
            $r['contradicts'] = in_array($r['subject'], $reported[(int) $r['school_id']] ?? [], true);
        }

        return [
            'exceptions' => $this->access->pendingExceptions(),
            'reports' => $reports,
            'disagreements' => $this->access->disagreements(),
        ];
    }

    /** @return array<string, mixed> */
    public function honestyReport(): array
    {
        return [
            'views' => $this->access->viewsByBasis(),
            'exceptions' => $this->access->decidedExceptions(),
            'reports' => $this->access->reportCounts(),
        ];
    }

    public function recordView(int $userId, int $lessonId, string $basis): void
    {
        $this->access->recordView($userId, $lessonId, $basis);
    }

    /**
     * @param list<array<string, mixed>> $students
     * @return array<int, \Closure(int): array{open: bool, basis: ?string, state: string}>
     */
    private function deciders(array $students): array
    {
        $this->parents ??= $this->access->parents();
        $parents = $this->parents;
        $userIds = array_map(static fn (array $s): int => (int) $s['user_id'], $students);
        $schoolIds = array_values(array_unique(array_filter(array_map(static fn (array $s): int => (int) $s['school_id'], $students))));
        $coverage = $this->access->coverage($schoolIds);
        $reported = $this->access->reportedSubjects($schoolIds);
        $reports = $this->access->reports($userIds);
        $exceptions = $this->access->exceptions($userIds);
        $subjects = $this->topics->subjectMap();

        $out = [];
        foreach ($students as $s) {
            $uid = (int) $s['user_id'];
            $school = (int) $s['school_id'];
            $cov = $coverage[$school] ?? [];
            $rep = $reports[$uid] ?? [];
            $exc = $exceptions[$uid] ?? [];
            $subj = $reported[$school] ?? [];
            $out[$uid] = static fn (int $topicId): array => self::decide(
                $topicId,
                $parents[$topicId] ?? null,
                $cov,
                $rep,
                $exc,
                in_array($subjects[$topicId] ?? '', $subj, true)
            );
        }

        return $out;
    }
}
