<?php

declare(strict_types=1);

namespace SmartCircle\Application\Learning;

use SmartCircle\Application\Calendar\CalendarService;
use SmartCircle\Application\Grades\GradeService;
use SmartCircle\Data\Repositories\LearningRepository;

/**
 * Impact: where each student started (entry quiz) and where they are now (this term's average),
 * plus how much they practise (lessons watched, Quick 5). Evidence for schools, partners and funders.
 * It is a guide, not a precise measure: the entry quiz and term work test different things.
 */
final class ImpactService
{
    public function __construct(
        private readonly LearningRepository $repo,
        private readonly GradeService $grades,
        private readonly CalendarService $calendar,
    ) {
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        $termId = $this->calendar->currentTermId();
        $rows = [];
        foreach ($this->repo->impactRows() as $r) {
            $g = $termId !== null ? $this->grades->forStudent((int) $r['id'], $termId) : null;
            $r['entry'] = (int) ($r['quiz_total'] ?? 0) > 0 ? round(100 * (int) $r['quiz_score'] / (int) $r['quiz_total'], 1) : null;
            $r['now'] = $g['overall'] ?? null;
            $r['quick5'] = (int) $r['q_answered'] > 0 ? round(100 * (int) $r['q_correct'] / (int) $r['q_answered']) : null;
            $rows[] = $r;
        }

        return ['rows' => $rows, 'summary' => self::summarise($rows)];
    }

    /**
     * Averages per form and how many students are above their starting point. Pure.
     * @param list<array<string, mixed>> $rows each with form, entry, now
     * @return array<string, mixed>
     */
    public static function summarise(array $rows): array
    {
        $forms = [];
        $compared = 0;
        $up = 0;
        foreach ($rows as $r) {
            $f = (int) $r['form'];
            $forms[$f] ??= ['students' => 0, 'entry' => [], 'now' => []];
            $forms[$f]['students']++;
            if ($r['entry'] !== null) {
                $forms[$f]['entry'][] = (float) $r['entry'];
            }
            if ($r['now'] !== null) {
                $forms[$f]['now'][] = (float) $r['now'];
            }
            if ($r['entry'] !== null && $r['now'] !== null) {
                $compared++;
                if ((float) $r['now'] > (float) $r['entry']) {
                    $up++;
                }
            }
        }
        $avg = static fn (array $v): ?float => $v === [] ? null : round(array_sum($v) / count($v), 1);
        foreach ($forms as &$f) {
            $f['entry'] = $avg($f['entry']);
            $f['now'] = $avg($f['now']);
        }
        unset($f);
        ksort($forms);

        return ['forms' => $forms, 'compared' => $compared, 'up' => $up];
    }
}
