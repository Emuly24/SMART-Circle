<?php

declare(strict_types=1);

namespace SmartCircle\Application\Calendar;

use SmartCircle\Data\Repositories\CalendarRepository;

/**
 * The academic calendar: measurable terms for grades, holidays (reminders pause), and
 * MANEB dates (countdowns, and later the revision period). The national calendar is
 * harmonised for every school; schools add their own dates (mid-term, mock exams).
 */
final class CalendarService
{
    public const EVENT_KINDS = [
        'jce' => 'MANEB JCE (Form 2)',
        'msce' => 'MANEB MSCE (Form 4)',
        'midterm' => 'Mid-term break',
        'mock' => 'Mock exams',
        'school_exam' => 'School exams',
        'holiday' => 'Holiday',
        'other' => 'Other',
    ];
    /** What partner schools may add for themselves. */
    public const SCHOOL_KINDS = ['midterm', 'mock', 'school_exam', 'holiday', 'other'];

    public function __construct(private readonly CalendarRepository $calendar)
    {
    }

    /**
     * Where a date falls. Pure.
     * @param list<array<string, mixed>> $terms in date order (opens_on, closes_on, number, year_name, id)
     * @return array{in_term: bool, term: ?array<string, mixed>, week: ?int, weeks: ?int, next: ?array<string, mixed>}
     */
    public static function position(string $date, array $terms): array
    {
        $prev = null;
        foreach ($terms as $t) {
            if ($date >= $t['opens_on'] && $date <= $t['closes_on']) {
                $days = (int) ((strtotime((string) $t['closes_on']) - strtotime((string) $t['opens_on'])) / 86400) + 1;
                $week = intdiv((int) ((strtotime($date) - strtotime((string) $t['opens_on'])) / 86400), 7) + 1;

                return ['in_term' => true, 'term' => $t, 'week' => $week, 'weeks' => (int) ceil($days / 7), 'next' => null];
            }
            if ($date < $t['opens_on']) {
                return ['in_term' => false, 'term' => $prev, 'week' => null, 'weeks' => null, 'next' => $t];
            }
            $prev = $t;
        }

        return ['in_term' => false, 'term' => $prev, 'week' => null, 'weeks' => null, 'next' => null];
    }

    /**
     * The term a piece of work counts towards: from the term's opening until the next term
     * opens (holiday work counts for the term just finished). Pure.
     * @param list<array<string, mixed>> $terms
     * @return array{0: string, 1: string} [from, until) as datetimes
     */
    public static function gradingRange(int $termId, array $terms): array
    {
        foreach ($terms as $i => $t) {
            if ((int) $t['id'] === $termId) {
                $until = isset($terms[$i + 1]) ? $terms[$i + 1]['opens_on'] : date('Y-m-d', (int) strtotime($t['closes_on'] . ' +70 days'));

                return [$t['opens_on'] . ' 00:00:00', $until . ' 00:00:00'];
            }
        }

        return ['1000-01-01 00:00:00', '1000-01-01 00:00:00'];
    }

    /** @return list<array<string, mixed>> */
    public function terms(): array
    {
        return $this->calendar->terms();
    }

    /** @return array<string, mixed>|null */
    public function term(int $id): ?array
    {
        return $this->calendar->term($id);
    }

    /** @return array{in_term: bool, term: ?array<string, mixed>, week: ?int, weeks: ?int, next: ?array<string, mixed>} */
    public function today(): array
    {
        return self::position(date('Y-m-d'), $this->calendar->terms());
    }

    /** The term to show by default: the current one, or the one just finished during holidays. */
    public function currentTermId(): ?int
    {
        $p = $this->today();
        $t = $p['term'] ?? $p['next'];

        return $t !== null ? (int) $t['id'] : null;
    }

    /**
     * School holidays: between terms, or after the last known term. Before the first known term
     * (or with no calendar at all) we cannot tell, so it is not treated as a holiday. Pure.
     * @param list<array<string, mixed>> $terms
     */
    public static function holiday(string $date, array $terms): bool
    {
        if ($terms === []) {
            return false;
        }
        $p = self::position($date, $terms);

        return !$p['in_term'] && $p['term'] !== null;
    }

    public function isHoliday(?string $date = null): bool
    {
        return self::holiday($date ?? date('Y-m-d'), $this->calendar->terms());
    }

    /** @return array{0: string, 1: string} */
    public function rangeFor(int $termId): array
    {
        return self::gradingRange($termId, $this->calendar->terms());
    }

    /**
     * Upcoming MANEB exams for a form, with days to go. Pure helper over events.
     * @param list<array<string, mixed>> $events
     * @return list<array{title: string, starts_on: string, ends_on: string, days: int}>
     */
    public static function manebFor(int $form, array $events, string $today): array
    {
        $out = [];
        foreach ($events as $e) {
            if (!in_array($e['kind'], ['jce', 'msce'], true) || $e['ends_on'] < $today) {
                continue;
            }
            $forms = $e['forms'] === null ? [] : array_map('intval', explode(',', (string) $e['forms']));
            if ($forms !== [] && !in_array($form, $forms, true)) {
                continue;
            }
            $out[] = ['title' => (string) $e['title'], 'starts_on' => (string) $e['starts_on'], 'ends_on' => (string) $e['ends_on'],
                'days' => max(0, (int) ceil((strtotime((string) $e['starts_on']) - strtotime($today)) / 86400))];
        }

        return $out;
    }

    /** @return list<array{title: string, starts_on: string, ends_on: string, days: int}> */
    public function maneb(int $form): array
    {
        return self::manebFor($form, $this->calendar->events(), date('Y-m-d'));
    }

    /** @return list<array<string, mixed>> national events plus one school's own (or every school's) */
    public function events(?int $schoolId = null, bool $allSchools = false): array
    {
        return $this->calendar->events($schoolId, $allSchools);
    }

    /** @return list<array<string, mixed>> */
    public function years(): array
    {
        return $this->calendar->years();
    }

    /**
     * Tutor: save a year's three terms (dates as YYYY-MM-DD).
     * @param array<string, mixed> $in name, t1_open, t1_close, t2_open, ... t3_close
     * @return array<string, string> errors
     */
    public function saveYear(array $in): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        $errors = [];
        if (preg_match('/^\d{4}\/\d{2}$/', $name) !== 1) {
            $errors['name'] = 'Name the year like 2027/28.';
        }
        $dates = [];
        $last = '';
        for ($n = 1; $n <= 3; $n++) {
            foreach (['open', 'close'] as $k) {
                $v = (string) ($in["t{$n}_$k"] ?? '');
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
                if ($d === false || $d->format('Y-m-d') !== $v) {
                    $errors["t{$n}_$k"] = 'Choose the date.';
                    continue;
                }
                if ($v <= $last) {
                    $errors["t{$n}_$k"] = 'Each date must come after the one before it.';
                }
                $last = $v;
                $dates[$n][$k] = $v;
            }
        }
        if ($errors !== []) {
            return $errors;
        }
        $year = null;
        foreach ($this->calendar->years() as $y) {
            if ($y['name'] === $name) {
                $year = (int) $y['id'];
            }
        }
        $year ??= $this->calendar->createYear($name, $dates[1]['open'], $dates[3]['close']);
        foreach ($dates as $n => $d) {
            $this->calendar->saveTerm($year, $n, $d['open'], $d['close']);
        }
        $this->calendar->updateYearDates($year);

        return [];
    }

    /**
     * Add a dated event. Schools may only add their own kinds of dates.
     * @param array<string, mixed> $in kind, title, starts_on, ends_on, forms (list)
     * @return array<string, string> errors
     */
    public function addEvent(?int $schoolId, array $in, int $userId): array
    {
        $kind = (string) ($in['kind'] ?? '');
        $title = trim((string) ($in['title'] ?? ''));
        $start = (string) ($in['starts_on'] ?? '');
        $end = (string) ($in['ends_on'] ?? '') ?: $start;
        $errors = [];
        $allowed = $schoolId === null ? array_keys(self::EVENT_KINDS) : self::SCHOOL_KINDS;
        if (!in_array($kind, $allowed, true)) {
            $errors['kind'] = 'Choose what kind of date it is.';
        }
        if (mb_strlen($title) < 3) {
            $title = self::EVENT_KINDS[$kind] ?? '';
        }
        foreach (['starts_on' => $start, 'ends_on' => $end] as $k => $v) {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            if ($d === false || $d->format('Y-m-d') !== $v) {
                $errors[$k] = 'Choose the date.';
            }
        }
        if (!isset($errors['starts_on']) && !isset($errors['ends_on']) && $end < $start) {
            $errors['ends_on'] = 'The end must be on or after the start.';
        }
        $forms = array_values(array_filter(array_map('intval', is_array($in['forms'] ?? null) ? $in['forms'] : []), static fn (int $f): bool => $f >= 1 && $f <= 4));
        if ($errors !== []) {
            return $errors;
        }
        $this->calendar->createEvent(['school_id' => $schoolId, 'kind' => $kind, 'title' => mb_substr($title, 0, 150),
            'starts_on' => $start, 'ends_on' => $end, 'forms' => $forms === [] ? null : implode(',', $forms)], $userId);

        return [];
    }

    /** Delete an event; a school may only delete its own. */
    public function deleteEvent(int $id, ?int $schoolId): bool
    {
        $e = $this->calendar->event($id);
        if ($e === null || ($schoolId !== null && (int) $e['school_id'] !== $schoolId)) {
            return false;
        }
        $this->calendar->deleteEvent($id);

        return true;
    }
}
