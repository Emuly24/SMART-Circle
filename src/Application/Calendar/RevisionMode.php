<?php

declare(strict_types=1);

namespace SmartCircle\Application\Calendar;

use SmartCircle\Data\Repositories\CalendarRepository;

/**
 * MANEB revision mode. From three weeks before a Form 2 (JCE) or Form 4 (MSCE) student's exam
 * until it ends, the whole exam syllabus (Forms 1-2 or Forms 3-4) opens for revision, even topics
 * their school has not reached. It is the one standing exception to the honesty rule, and every
 * lesson watched this way is recorded with the basis "revision".
 */
final class RevisionMode
{
    public const DAYS_BEFORE = 21;
    public const SYLLABUS = ['jce' => [1, 2], 'msce' => [3, 4]];
    public const EXAM_FORMS = ['jce' => 2, 'msce' => 4];

    /** @var array<int, array<string, mixed>|null> */
    private array $cache = [];

    public function __construct(private readonly ?CalendarRepository $calendar = null, private readonly ?string $today = null)
    {
    }

    /**
     * The revision window a form is in on a day, if any. Pure.
     * @param list<array<string, mixed>> $events calendar events (kind, forms, starts_on, ends_on)
     * @return array{exam: string, title: string, starts_on: string, ends_on: string, opens_on: string, days: int, forms: list<int>}|null
     */
    public static function window(int $form, array $events, string $today): ?array
    {
        foreach ($events as $e) {
            $kind = (string) $e['kind'];
            if (!isset(self::SYLLABUS[$kind]) || !empty($e['school_id'])) {
                continue;
            }
            $forms = $e['forms'] === null || $e['forms'] === '' ? [self::EXAM_FORMS[$kind]] : array_map('intval', explode(',', (string) $e['forms']));
            if (!in_array($form, $forms, true)) {
                continue;
            }
            $opens = date('Y-m-d', (int) strtotime($e['starts_on'] . ' -' . self::DAYS_BEFORE . ' days'));
            if ($today >= $opens && $today <= $e['ends_on']) {
                return [
                    'exam' => $kind,
                    'title' => (string) $e['title'],
                    'starts_on' => (string) $e['starts_on'],
                    'ends_on' => (string) $e['ends_on'],
                    'opens_on' => $opens,
                    'days' => max(0, (int) ceil((strtotime((string) $e['starts_on']) - strtotime($today)) / 86400)),
                    'forms' => self::SYLLABUS[$kind],
                ];
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function for(int $form): ?array
    {
        if (!array_key_exists($form, $this->cache)) {
            $this->cache[$form] = $this->calendar === null ? null
                : self::window($form, $this->calendar->events(), $this->today ?? date('Y-m-d'));
        }

        return $this->cache[$form];
    }

    /**
     * Adds 'forms' (which forms' topics the student may browse) and 'revision' to a student profile.
     * @param array<string, mixed>|null $profile
     * @return array<string, mixed>|null
     */
    public function apply(?array $profile): ?array
    {
        if ($profile === null || !isset($profile['form'])) {
            return $profile;
        }
        $w = ($profile['status'] ?? 'active') === 'active' ? $this->for((int) $profile['form']) : null;

        return $profile + ['forms' => $w['forms'] ?? [(int) $profile['form']], 'revision' => $w];
    }
}
