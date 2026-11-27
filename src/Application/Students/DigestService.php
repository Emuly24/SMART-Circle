<?php

declare(strict_types=1);

namespace SmartCircle\Application\Students;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Data\Repositories\EngagementRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Support\Subjects;

/**
 * The weekly digest, every Monday: each student hears what is new and open to them (honesty rule),
 * what is due and what feedback came back; the tutor hears what is waiting. It arrives in the bell and,
 * for those who want it, by email.
 */
final class DigestService
{
    public function __construct(
        private readonly EngagementRepository $repo,
        private readonly StudentRepository $students,
        private readonly AccessService $access,
        private readonly NotificationService $notifications,
        private readonly SeatCheckService $seats,
    ) {
    }

    /** @return array{students: int} */
    public function send(): array
    {
        $new = $this->repo->newThisWeek();
        $n = 0;
        foreach ($this->repo->seats() as $s) {
            $d = $this->forStudent((int) $s['id'], (string) $s['full_name'], $new);
            if ($d === null) {
                continue;
            }
            $this->notifications->notify([(int) $s['id']], 'digest', 'Your week at SMART Circle', self::studentText($d), '/dashboard');
            $n++;
        }
        $this->notifications->notifyTutors('digest', 'Your week at SMART Circle', self::tutorText($this->repo->tutorWaiting(), count($this->seats->quiet())), '/dashboard');

        return ['students' => $n];
    }

    /**
     * What one student would hear this week.
     * @param list<array<string, mixed>>|null $new
     * @return array{first: string, new: list<array<string, mixed>>, due: list<array<string, mixed>>, marked: int, active: int}|null
     */
    public function forStudent(int $userId, string $fullName, ?array $new = null): ?array
    {
        $profile = $this->access->withRevision($this->students->profile($userId));
        $decide = $this->access->forStudent($userId);
        if ($profile === null || $decide === null) {
            return null;
        }
        $subjects = Subjects::forTrack((string) $profile['track']);
        $open = [];
        foreach ($new ?? $this->repo->newThisWeek() as $item) {
            if (in_array((int) $item['form'], array_map('intval', $profile['forms']), true) && in_array($item['subject'], $subjects, true)
                && $item['topic_id'] !== null && ($decide((int) $item['topic_id'])['open'] ?? false)) {
                $open[] = $item;
            }
        }

        return [
            'first' => explode(' ', trim($fullName))[0],
            'new' => $open,
            'due' => $this->repo->dueSoon($userId, (int) $profile['form']),
            'marked' => $this->repo->markedThisWeek($userId),
            'active' => $this->repo->activeThisWeek($userId),
        ];
    }

    /** Pure. @param array<string, mixed> $d */
    public static function studentText(array $d): string
    {
        $lines = [];
        $lessons = count(array_filter($d['new'], static fn (array $i): bool => $i['kind'] === 'lesson'));
        $notes = count($d['new']) - $lessons;
        if ($lessons + $notes > 0) {
            $parts = array_filter([$lessons > 0 ? $lessons . ' new lesson' . ($lessons === 1 ? '' : 's') : '', $notes > 0 ? $notes . ' new set' . ($notes === 1 ? '' : 's') . ' of notes' : '']);
            $titles = array_slice(array_map(static fn (array $i): string => (string) $i['title'], $d['new']), 0, 3);
            $lines[] = implode(' and ', $parts) . ' for you: ' . implode(', ', $titles) . (count($d['new']) > 3 ? ' and more' : '') . '.';
        }
        if ($d['due'] !== []) {
            $first = $d['due'][0];
            $lines[] = count($d['due']) . ' piece' . (count($d['due']) === 1 ? '' : 's') . ' of work due this week (first: ' . $first['title'] . ', ' . date('D j M', (int) strtotime((string) $first['closes_at'])) . ').';
        }
        if ($d['marked'] > 0) {
            $lines[] = 'Feedback is ready on ' . $d['marked'] . ' piece' . ($d['marked'] === 1 ? '' : 's') . ' of your work.';
        }
        if ($lines === []) {
            $lines[] = 'Nothing new this week: a good time to revise a lesson or ask for a topic your school has covered.';
        }
        $lines[] = $d['active'] > 0 ? 'Well done for studying last week. Keep it up!' : 'Even 20 minutes this week will make a difference.';

        return 'Hello ' . $d['first'] . '. ' . implode(' ', $lines);
    }

    /** Pure. @param array<string, int> $w */
    public static function tutorText(array $w, int $quiet): string
    {
        $parts = [];
        foreach (['applications' => 'application(s) to decide', 'to_mark' => 'piece(s) of work to mark', 'rechecks' => 're-check request(s)',
            'requests' => 'open topic request(s)', 'guardians' => 'guardian(s) not yet confirmed'] as $k => $label) {
            if (($w[$k] ?? 0) > 0) {
                $parts[] = $w[$k] . ' ' . $label;
            }
        }
        if ($quiet > 0) {
            $parts[] = $quiet . ' quiet student(s) to check on (Students, Seat activity)';
        }

        return $parts === [] ? 'All clear: nothing is waiting for you this week.' : 'Waiting for you: ' . implode('; ', $parts) . '.';
    }
}
