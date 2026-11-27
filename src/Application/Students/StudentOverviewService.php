<?php

declare(strict_types=1);

namespace SmartCircle\Application\Students;

use SmartCircle\Application\Calendar\CalendarService;
use SmartCircle\Application\Grades\GradeService;
use SmartCircle\Data\Repositories\OverviewRepository;
use SmartCircle\Support\PhoneNumber;

/** The tutor's view of every student: activity, gentle early-warning flags and ways to reach them. */
final class StudentOverviewService
{
    public const CHANNELS = ['phone' => 'Phone call', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email', 'visit' => 'Visit', 'letter' => 'Letter'];

    public function __construct(
        private readonly OverviewRepository $repo,
        private readonly GradeService $grades,
        private readonly CalendarService $calendar,
    ) {
    }

    /** @return list<array<string, mixed>> every active student with figures and flags */
    public function all(): array
    {
        $termId = $this->termId();
        $last = $this->repo->lastContacts();
        $holiday = $this->calendar->isHoliday();
        $out = [];
        foreach ($this->repo->activity() as $id => $s) {
            $g = $termId !== null ? $this->grades->forStudent($id, $termId) : null;
            $s['overall'] = $g['overall'] ?? null;
            $s['overall_grade'] = $g['overall_grade'] ?? null;
            $s['last_contact_at'] = $last[$id] ?? null;
            $s['flags'] = self::flags($s, $holiday);
            $out[] = $s;
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function one(int $userId): ?array
    {
        foreach ($this->all() as $s) {
            if ((int) $s['id'] === $userId) {
                $s['timeline'] = $this->repo->timeline($userId);
                $s['contacts'] = $this->repo->contacts($userId);
                $s['links'] = self::links($s);
                $termId = $this->termId();
                $s['grades'] = $termId !== null ? $this->grades->forStudent($userId, $termId) : null;

                return $s;
            }
        }

        return null;
    }

    private function termId(): ?int
    {
        return $this->calendar->currentTermId();
    }

    /**
     * Early-warning flags, worded kindly: things worth a friendly check-in, not judgements.
     * @param array<string, mixed> $s
     * @return list<array{key: string, label: string}>
     */
    public static function flags(array $s, bool $holiday = false, ?int $now = null): array
    {
        $now ??= time();
        $flags = [];
        $lastSeen = $s['last_login_at'] !== null ? strtotime((string) $s['last_login_at']) : null;
        if (!$holiday && ($lastSeen === null || $now - $lastSeen > 7 * 86400)) {
            $flags[] = ['key' => 'quiet', 'label' => $lastSeen === null ? 'Has not logged in yet' : 'Not seen for over a week'];
        }
        if ((int) ($s['missed_30d'] ?? 0) > 0) {
            $n = (int) $s['missed_30d'];
            $flags[] = ['key' => 'missed', 'label' => $n . ' piece' . ($n === 1 ? '' : 's') . ' of work missed this month'];
        }
        if ($s['overall'] !== null && (float) $s['overall'] < 40) {
            $flags[] = ['key' => 'low', 'label' => 'Term average under 40%'];
        }
        if (!$holiday && $lastSeen !== null && $now - $lastSeen <= 7 * 86400 && (int) ($s['lessons_14d'] ?? 0) === 0 && (int) ($s['work_30d'] ?? 0) === 0) {
            $flags[] = ['key' => 'idle', 'label' => 'Logs in but has not watched or handed in anything lately'];
        }

        return $flags;
    }

    /**
     * Click-to-contact links for the student and guardian. Opening a link records nothing;
     * the tutor records the conversation afterwards.
     * @param array<string, mixed> $s
     * @return array<string, list<array{label: string, href: string, channel: string}>>
     */
    public static function links(array $s): array
    {
        $first = explode(' ', trim((string) $s['full_name']))[0];
        $out = ['student' => [], 'guardian' => []];
        $phone = PhoneNumber::normalize((string) $s['phone']);
        if ($phone !== null) {
            $text = rawurlencode('Hello ' . $first . ', this is your SMART Circle tutor. ');
            $out['student'][] = ['label' => 'Call', 'href' => 'tel:' . $phone, 'channel' => 'phone'];
            $out['student'][] = ['label' => 'WhatsApp', 'href' => 'https://wa.me/' . ltrim($phone, '+') . '?text=' . $text, 'channel' => 'whatsapp'];
            $out['student'][] = ['label' => 'SMS', 'href' => 'sms:' . $phone . '?body=' . $text, 'channel' => 'sms'];
        }
        if (!empty($s['email']) && filter_var($s['email'], FILTER_VALIDATE_EMAIL)) {
            $out['student'][] = ['label' => 'Email', 'href' => 'mailto:' . $s['email'] . '?subject=' . rawurlencode('SMART Circle'), 'channel' => 'email'];
        }
        $g = PhoneNumber::normalize((string) ($s['guardian_phone'] ?? ''));
        if ($g !== null) {
            $text = rawurlencode('Hello' . (trim((string) ($s['guardian_name'] ?? '')) !== '' ? ' ' . trim((string) $s['guardian_name']) : '')
                . ', this is the SMART Circle tutor about ' . $first . '. ');
            $out['guardian'][] = ['label' => 'Call', 'href' => 'tel:' . $g, 'channel' => 'phone'];
            $out['guardian'][] = ['label' => 'WhatsApp', 'href' => 'https://wa.me/' . ltrim($g, '+') . '?text=' . $text, 'channel' => 'whatsapp'];
        }

        return $out;
    }

    /** @return array<string, string> errors */
    public function logContact(int $userId, string $who, string $channel, string $summary, int $tutorId): array
    {
        $errors = [];
        if (!isset(self::CHANNELS[$channel])) {
            $errors['channel'] = 'Choose how you were in touch.';
        }
        $summary = trim($summary);
        if ($summary === '' || mb_strlen($summary) > 450) {
            $errors['summary'] = 'Write a short note of what you discussed (up to 450 characters).';
        }
        if (!isset($this->repo->activity()[$userId])) {
            $errors['channel'] = 'That student was not found.';
        }
        if ($errors === []) {
            $prefix = $who === 'guardian' ? '[Guardian] ' : '';
            $this->repo->logContact($userId, $channel === 'sms' ? 'phone' : $channel, $prefix . ($channel === 'sms' ? '[SMS] ' : '') . $summary, $tutorId);
        }

        return $errors;
    }
}
