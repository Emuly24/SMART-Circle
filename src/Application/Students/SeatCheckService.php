<?php

declare(strict_types=1);

namespace SmartCircle\Application\Students;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\Templates\TemplateService;
use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\EngagementRepository;

/**
 * Seats are precious: when an admitted student goes quiet, they first get a friendly automatic nudge,
 * then the tutor sees them on the seat activity page to check in, pause the checks for a good reason,
 * or (as a last step) free the seat for someone on the waiting list. Every step is kept.
 */
final class SeatCheckService
{
    public const NUDGE_AFTER_DAYS = 14;
    public const REVIEW_AFTER_DAYS = 21;

    public function __construct(
        private readonly EngagementRepository $repo,
        private readonly NotificationService $notifications,
        private readonly TemplateService $templates,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /**
     * Students quiet for long enough to need the tutor's attention, quietest first.
     * @return list<array<string, mixed>>
     */
    public function quiet(?int $now = null): array
    {
        $now ??= time();
        $out = [];
        foreach ($this->repo->seats() as $s) {
            $s = self::describe($s, $now);
            if ($s['state'] !== 'ok') {
                $s['waiting'] = $this->repo->waiting((int) $s['form'], (string) $s['gender']);
                $out[] = $s;
            }
        }
        usort($out, static fn (array $a, array $b): int => $b['days'] <=> $a['days']);

        return $out;
    }

    /**
     * How long a student has been quiet, and what that means. Pure.
     * ok | paused | nudge (quiet 14+ days) | review (quiet 21+ days)
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    public static function describe(array $s, int $now): array
    {
        $seen = strtotime((string) $s['last_seen_at']);
        $since = $seen !== false && $seen > 86400 ? $seen : (int) strtotime((string) $s['admitted_at']);
        $s['never'] = $seen === false || $seen <= 86400;
        $s['days'] = max(0, intdiv($now - $since, 86400));
        $paused = $s['last_check'] === 'pause' && $s['paused_until'] !== null && strtotime((string) $s['paused_until'] . ' 23:59:59') >= $now;
        $s['state'] = $paused ? 'paused' : ($s['days'] >= self::REVIEW_AFTER_DAYS ? 'review' : ($s['days'] >= self::NUDGE_AFTER_DAYS ? 'nudge' : 'ok'));

        return $s;
    }

    /** The daily job: a friendly automatic nudge to each student quiet for 14+ days (at most every 14 days). @return int nudged */
    public function autoNudge(?int $now = null): int
    {
        $now ??= time();
        $n = 0;
        foreach ($this->repo->seats() as $s) {
            $s = self::describe($s, $now);
            $recent = $s['last_check'] === 'nudge' && strtotime((string) $s['last_check_at']) > $now - self::NUDGE_AFTER_DAYS * 86400;
            if (in_array($s['state'], ['nudge', 'review'], true) && !$recent) {
                $this->nudge((int) $s['id'], null);
                $n++;
            }
        }

        return $n;
    }

    public function nudge(int $userId, ?int $tutorId): void
    {
        $s = $this->find($userId);
        if ($s === null) {
            return;
        }
        $first = explode(' ', trim((string) $s['full_name']))[0];
        $msg = $this->templates->render('student_nudge', ['student' => $first]);
        $this->notifications->notify([$userId], 'nudge', $msg['subject'], $msg['body'], '/dashboard');
        $this->repo->addCheck($userId, 'nudge', $tutorId === null ? 'Automatic nudge' : 'Nudged by the tutor', null, $tutorId);
    }

    /** @return string error ('' when saved) */
    public function pause(int $userId, string $until, string $reason, int $tutorId): string
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $until);
        if ($day === false || $day->format('Y-m-d') !== $until || $day <= new \DateTimeImmutable('today') || $day > new \DateTimeImmutable('+120 days')) {
            return 'Choose a date within the next four months.';
        }
        if (mb_strlen(trim($reason)) < 3) {
            return 'Please note the reason (for example: sick, exams at school, no phone this month).';
        }
        $this->repo->addCheck($userId, 'pause', mb_substr(trim($reason), 0, 500), $until, $tutorId);

        return '';
    }

    /** @return string error ('' when released) */
    public function release(int $userId, string $reason, int $tutorId, string $ip): string
    {
        $s = $this->find($userId);
        if ($s === null) {
            return 'This student is not active.';
        }
        if (mb_strlen(trim($reason)) < 5) {
            return 'Please write why the seat is being freed. It is kept with the student\'s record.';
        }
        $this->repo->release($userId);
        $this->repo->addCheck($userId, 'released', mb_substr(trim($reason), 0, 500), null, $tutorId);
        $this->audit->record($tutorId, 'seat_released', (string) $s['full_name'], $ip);

        return '';
    }

    /** @return list<array<string, mixed>> */
    public function history(int $userId): array
    {
        return $this->repo->checks($userId);
    }

    /** @return array<string, mixed>|null */
    private function find(int $userId): ?array
    {
        foreach ($this->repo->seats() as $s) {
            if ((int) $s['id'] === $userId) {
                return $s;
            }
        }

        return null;
    }
}
