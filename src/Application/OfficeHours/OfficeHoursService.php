<?php

declare(strict_types=1);

namespace SmartCircle\Application\OfficeHours;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\OfficeHoursRepository;

/**
 * Office hours: the tutor offers times when students (and alumni) can book a call, a WhatsApp
 * call or a quick message exchange about something they are stuck on. No live streaming.
 */
final class OfficeHoursService
{
    public const MODES = ['phone' => 'Phone call', 'whatsapp' => 'WhatsApp call', 'messages' => 'Messages in SMART Circle'];
    public const AUDIENCES = ['all' => 'All students', 'form:1' => 'Form 1', 'form:2' => 'Form 2', 'form:3' => 'Form 3', 'form:4' => 'Form 4', 'alumni' => 'Alumni'];

    public function __construct(
        private readonly OfficeHoursRepository $hours,
        private readonly NotificationService $notifications,
        /** @var \Closure(string $audience): list<int> */
        private readonly \Closure $audienceIds,
    ) {
    }

    /**
     * Validates a new slot. Pure.
     * @param array<string, mixed> $in date, start, end (HH:MM), mode, capacity, audience, note
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validate(array $in, string $now): array
    {
        $errors = [];
        $date = (string) ($in['date'] ?? '');
        $start = (string) ($in['start'] ?? '');
        $end = (string) ($in['end'] ?? '');
        $startsAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && preg_match('/^\d{2}:\d{2}$/', $start) ? $date . ' ' . $start . ':00' : null;
        $endsAt = $startsAt !== null && preg_match('/^\d{2}:\d{2}$/', $end) ? $date . ' ' . $end . ':00' : null;
        if ($startsAt === null || strtotime($startsAt) === false) {
            $errors['date'] = 'Give the day and start time.';
        } elseif ($startsAt <= $now) {
            $errors['date'] = 'Choose a time in the future.';
        }
        if ($endsAt === null || ($startsAt !== null && $endsAt <= $startsAt)) {
            $errors['end'] = 'The end time must be after the start.';
        } elseif ($startsAt !== null && strtotime($endsAt) - strtotime($startsAt) > 3 * 3600) {
            $errors['end'] = 'Keep a session to three hours or less.';
        }
        $mode = (string) ($in['mode'] ?? '');
        if (!isset(self::MODES[$mode])) {
            $errors['mode'] = 'Choose how you will talk.';
        }
        $capacity = (int) ($in['capacity'] ?? 0);
        if ($capacity < 1 || $capacity > 20) {
            $errors['capacity'] = 'Between 1 and 20 students.';
        }
        $audience = (string) ($in['audience'] ?? 'all');
        if (!isset(self::AUDIENCES[$audience])) {
            $errors['audience'] = 'Choose who can book.';
        }
        $note = trim((string) ($in['note'] ?? ''));

        return ['errors' => $errors, 'clean' => ['starts_at' => $startsAt, 'ends_at' => $endsAt, 'mode' => $mode, 'capacity' => $capacity,
            'audience' => $audience, 'note' => $note === '' ? null : mb_substr($note, 0, 300)]];
    }

    /** May someone with this role and form book a slot for this audience? Pure. */
    public static function eligible(string $audience, string $role, ?int $form): bool
    {
        if ($audience === 'alumni') {
            return $role === 'alumni';
        }
        if ($role !== 'student') {
            return false;
        }

        return $audience === 'all' || $audience === 'form:' . $form;
    }

    /** @return array<string, string> errors */
    public function create(array $in, int $tutorId): array
    {
        $v = self::validate($in, date('Y-m-d H:i:s'));
        if ($v['errors'] !== []) {
            return $v['errors'];
        }
        $id = $this->hours->create($v['clean'], $tutorId);
        $when = date('D j M, H:i', (int) strtotime((string) $v['clean']['starts_at']));
        $this->notifications->notify(($this->audienceIds)($v['clean']['audience']), 'office_hours', 'Office hours: ' . $when,
            self::MODES[$v['clean']['mode']] . '. Book a place if you are stuck on something.', '/office-hours#slot-' . $id);

        return [];
    }

    /** @return list<array<string, mixed>> upcoming slots this person may book, with their own booking */
    public function forPerson(int $userId, string $role, ?int $form): array
    {
        $mine = $this->hours->bookingsOf($userId);
        $out = [];
        foreach ($this->hours->upcoming(date('Y-m-d H:i:s')) as $s) {
            if (self::eligible((string) $s['audience'], $role, $form) || isset($mine[(int) $s['id']])) {
                $out[] = $s + ['mine' => $mine[(int) $s['id']] ?? null];
            }
        }

        return $out;
    }

    /** @return string|null error */
    public function book(int $slotId, int $userId, string $role, ?int $form, string $question): ?string
    {
        $s = $this->hours->slot($slotId);
        if ($s === null || $s['status'] !== 'open' || !self::eligible((string) $s['audience'], $role, $form)) {
            return 'That time is not available.';
        }
        if ((string) $s['starts_at'] <= date('Y-m-d H:i:s')) {
            return 'That session has already started.';
        }
        $question = trim($question);
        if (mb_strlen($question) < 5 || mb_strlen($question) > 500) {
            return 'Say in a sentence what you would like help with (up to 500 characters).';
        }
        if (!$this->hours->book($slotId, $userId, $question, (int) $s['capacity'])) {
            return 'Sorry, that session is full. Try another time.';
        }
        $this->notifications->notifyTutors('office_hours', 'Office hours booked: ' . date('D j M, H:i', (int) strtotime((string) $s['starts_at'])), $question, '/studio/office-hours/' . $slotId);

        return null;
    }

    /** @return string|null error */
    public function cancelBooking(int $slotId, int $userId): ?string
    {
        $b = $this->hours->booking($slotId, $userId);
        $s = $this->hours->slot($slotId);
        if ($b === null || $s === null || $b['status'] !== 'booked') {
            return 'There is no booking to cancel.';
        }
        if ((string) $s['starts_at'] <= date('Y-m-d H:i:s')) {
            return 'The session has started; please message the tutor instead.';
        }
        $this->hours->setBookingStatus((int) $b['id'], 'cancelled');

        return null;
    }

    /** @return array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>} */
    public function forTutor(): array
    {
        $now = date('Y-m-d H:i:s');

        return ['upcoming' => $this->hours->upcoming($now, true), 'past' => $this->hours->past($now)];
    }

    /** @return array<string, mixed>|null */
    public function slot(int $id): ?array
    {
        $s = $this->hours->slot($id);

        return $s === null ? null : $s + ['bookings' => $this->hours->bookings($id)];
    }

    public function cancelSlot(int $id): bool
    {
        $s = $this->slot($id);
        if ($s === null || $s['status'] !== 'open') {
            return false;
        }
        $this->hours->cancelSlot($id);
        $booked = array_map(static fn (array $b): int => (int) $b['user_id'], array_filter($s['bookings'], static fn (array $b): bool => $b['status'] === 'booked'));
        $this->notifications->notify(array_values($booked), 'office_hours', 'Office hours cancelled: ' . date('D j M, H:i', (int) strtotime((string) $s['starts_at'])),
            'Sorry, this session will not take place. Please book another time or send the tutor a message.', '/office-hours');

        return true;
    }

    public function markBooking(int $bookingId, string $status): ?int
    {
        $b = $this->hours->bookingById($bookingId);
        if ($b === null || !in_array($status, ['done', 'missed', 'booked'], true)) {
            return null;
        }
        $this->hours->setBookingStatus($bookingId, $status);

        return (int) $b['slot_id'];
    }
}
