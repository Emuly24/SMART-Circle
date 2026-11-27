<?php

declare(strict_types=1);

namespace SmartCircle\Application\Grades;

use SmartCircle\Application\Admissions\DocumentService;
use SmartCircle\Application\Calendar\CalendarService;
use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Data\Repositories\GradeRepository;
use SmartCircle\Support\Subjects;

/**
 * Grades. Every marked piece of work is kept and counts towards a term grade per subject:
 * exercises, assignments, tests and exams are averaged within their category, then weighted
 * (default 15 / 20 / 25 / 40; categories with no work yet are left out and the rest re-weighted).
 * Forms 1-2 get JCE letters (A-F), Forms 3-4 MSCE points (1-9). Terms come from the calendar.
 */
final class GradeService
{
    public const CATEGORIES = [
        'exercise' => 'Exercises',
        'assignment' => 'Assignments',
        'test' => 'Tests',
        'exam' => 'Exams',
    ];
    public const DEFAULT_WEIGHTS = ['exercise' => 15, 'assignment' => 20, 'test' => 25, 'exam' => 40];

    public function __construct(
        private readonly GradeRepository $grades,
        private readonly CalendarService $calendar,
        private readonly AccessService $access,
        private readonly DocumentService $documents,
        private readonly NotificationService $notifications,
    ) {
    }

    public static function categoryOf(string $kind): string
    {
        return match ($kind) {
            'exam' => 'exam',
            'test' => 'test',
            'assignment' => 'assignment',
            default => 'exercise',   // weekly_check, note_check
        };
    }

    public static function scaleFor(int $form): string
    {
        return $form <= 2 ? 'jce' : 'msce';
    }

    /**
     * One subject's term grade from its marked work. Pure.
     * @param list<array{kind: string, score: float|string, max_score: float|string}> $work
     * @param array<string, int|float> $weights
     * @return array{categories: array<string, array{percent: float, count: int}>, percent: ?float}
     */
    public static function compute(array $work, array $weights): array
    {
        $sums = [];
        foreach ($work as $w) {
            if ((float) $w['max_score'] <= 0) {
                continue;
            }
            $cat = self::categoryOf((string) $w['kind']);
            $sums[$cat][] = 100 * (float) $w['score'] / (float) $w['max_score'];
        }
        $cats = [];
        $weighted = 0.0;
        $weightUsed = 0.0;
        foreach (self::CATEGORIES as $cat => $label) {
            if (!isset($sums[$cat])) {
                continue;
            }
            $pct = array_sum($sums[$cat]) / count($sums[$cat]);
            $cats[$cat] = ['percent' => round($pct, 1), 'count' => count($sums[$cat])];
            $wt = max(0.0, (float) ($weights[$cat] ?? 0));
            $weighted += $pct * $wt;
            $weightUsed += $wt;
        }

        return ['categories' => $cats, 'percent' => $weightUsed > 0 ? round($weighted / $weightUsed, 1) : null];
    }

    /**
     * @param list<array{grade: string, min_percent: int, descriptor: string}> $bands best first
     * @return array{grade: string, descriptor: string}|null
     */
    public static function grade(?float $percent, array $bands): ?array
    {
        if ($percent === null) {
            return null;
        }
        foreach ($bands as $b) {
            if ($percent >= $b['min_percent']) {
                return ['grade' => $b['grade'], 'descriptor' => $b['descriptor']];
            }
        }

        return null;
    }

    /** @return array<string, int> */
    public function weights(): array
    {
        $saved = json_decode((string) $this->grades->setting('grade_weights'), true);
        $out = [];
        foreach (self::DEFAULT_WEIGHTS as $cat => $default) {
            $out[$cat] = is_array($saved) && isset($saved[$cat]) && is_numeric($saved[$cat]) ? (int) $saved[$cat] : $default;
        }

        return $out;
    }

    /** @return array<string, list<array{grade: string, min_percent: int, descriptor: string}>> */
    public function bands(): array
    {
        return $this->grades->bands();
    }

    /**
     * @param array<string, mixed> $weights category => percent
     * @param array<string, mixed> $bands "scale:grade" => ['min' => ..., 'descriptor' => ...]
     * @return array<string, string> errors
     */
    public function saveSettings(array $weights, array $bands): array
    {
        $clean = [];
        foreach (self::CATEGORIES as $cat => $label) {
            $v = $weights[$cat] ?? null;
            if (!is_numeric($v) || (int) $v < 0 || (int) $v > 100) {
                return ['weights' => 'Each weight must be a number from 0 to 100.'];
            }
            $clean[$cat] = (int) $v;
        }
        if (array_sum($clean) !== 100) {
            return ['weights' => 'The weights must add up to 100 (now ' . array_sum($clean) . ').'];
        }
        foreach ($this->grades->bands() as $scale => $list) {
            $prev = 101;
            $min = 0;
            foreach ($list as $b) {
                $in = $bands[$scale . ':' . $b['grade']] ?? null;
                $min = is_array($in) && is_numeric($in['min'] ?? null) ? (int) $in['min'] : $b['min_percent'];
                if ($min >= $prev || $min < 0 || $min > 100) {
                    return ['bands' => 'Grade boundaries must go down from the best grade to the lowest (' . strtoupper($scale) . ' ' . $b['grade'] . ').'];
                }
                $prev = $min;
            }
            if ($min !== 0) {
                return ['bands' => 'The lowest ' . strtoupper($scale) . ' grade must start at 0%.'];
            }
        }
        $this->grades->saveSetting('grade_weights', (string) json_encode($clean));
        foreach ($this->grades->bands() as $scale => $list) {
            foreach ($list as $b) {
                $in = $bands[$scale . ':' . $b['grade']] ?? [];
                $desc = trim((string) ($in['descriptor'] ?? $b['descriptor'])) ?: $b['descriptor'];
                $min = is_numeric($in['min'] ?? null) ? (int) $in['min'] : $b['min_percent'];
                $this->grades->saveBand($scale, $b['grade'], $min, mb_substr($desc, 0, 40));
            }
        }

        return [];
    }

    /**
     * A student's grades for a term: per subject (their track's subjects), plus an overall average.
     * @param array<string, mixed> $student user_id, form, track
     * @return array<string, mixed>
     */
    public function termGrades(array $student, int $termId, ?array $workForAll = null): array
    {
        [$from, $until] = $this->calendar->rangeFor($termId);
        $work = $workForAll !== null
            ? array_values(array_filter($workForAll, static fn (array $w): bool => (int) $w['user_id'] === (int) $student['user_id']))
            : $this->grades->markedWork($from, $until, (int) $student['user_id']);
        $weights = $this->weights();
        $bands = $this->grades->bands()[self::scaleFor((int) $student['form'])] ?? [];
        $subjects = [];
        $percents = [];
        foreach (Subjects::forTrack((string) $student['track']) as $subject) {
            $mine = array_values(array_filter($work, static fn (array $w): bool => $w['subject'] === $subject));
            $c = self::compute($mine, $weights);
            $subjects[$subject] = $c + ['grade' => self::grade($c['percent'], $bands), 'work' => $mine];
            if ($c['percent'] !== null) {
                $percents[] = $c['percent'];
            }
        }
        $overall = $percents === [] ? null : round(array_sum($percents) / count($percents), 1);

        return [
            'term_id' => $termId,
            'scale' => self::scaleFor((int) $student['form']),
            'subjects' => $subjects,
            'overall' => $overall,
            'overall_grade' => self::grade($overall, $bands),
            'graded' => count($percents),
            'subject_count' => count($subjects),
        ];
    }

    /** @return array<string, mixed>|null the logged-in student's own grades */
    public function forStudent(int $userId, int $termId): ?array
    {
        $student = $this->student($userId);

        return $student === null ? null : $this->termGrades($student, $termId);
    }

    /**
     * Gradebook: every active student in a form with their term grades.
     * @return list<array<string, mixed>>
     */
    public function gradebook(int $termId, int $form): array
    {
        [$from, $until] = $this->calendar->rangeFor($termId);
        $work = $this->grades->markedWork($from, $until);
        $reports = $this->grades->reportsForTerm($termId);
        $rows = [];
        foreach ($this->access->allStudents() as $s) {
            if ((int) $s['form'] !== $form) {
                continue;
            }
            $rows[] = ['student' => $s, 'grades' => $this->termGrades($s, $termId, $work), 'report' => $reports[(int) $s['user_id']] ?? null];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['student']['full_name'], (string) $b['student']['full_name']));

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function student(int $userId): ?array
    {
        foreach ($this->access->allStudents() as $s) {
            if ((int) $s['user_id'] === $userId) {
                return $s;
            }
        }

        return null;
    }

    /**
     * Issue term reports (a snapshot that never changes afterwards) for every student in a form,
     * each with a verifiable printable document. Re-issuing replaces the snapshot.
     * @param array<string, mixed> $comments user id => tutor comment
     */
    public function issueReports(int $termId, int $form, array $comments, int $tutorId): int
    {
        $term = $this->calendar->term($termId);
        if ($term === null) {
            return 0;
        }
        $count = 0;
        foreach ($this->gradebook($termId, $form) as $row) {
            $s = $row['student'];
            $g = $row['grades'];
            $subjects = [];
            foreach ($g['subjects'] as $key => $sub) {
                $subjects[$key] = ['name' => Subjects::NAMES[$key] ?? $key, 'percent' => $sub['percent'], 'grade' => $sub['grade'],
                    'categories' => $sub['categories']];
            }
            $comment = trim((string) ($comments[(int) $s['user_id']] ?? ''));
            $data = ['full_name' => $s['full_name'], 'form' => (int) $s['form'], 'scale' => $g['scale'], 'term' => (int) $term['number'],
                'year' => $term['year_name'], 'subjects' => $subjects, 'overall' => $g['overall'], 'overall_grade' => $g['overall_grade'],
                'graded' => $g['graded'], 'subject_count' => $g['subject_count'],
                'weights' => $this->weights(), 'comment' => $comment];
            $reportId = $this->grades->saveReport((int) $s['user_id'], $termId, $data, $comment === '' ? null : $comment, $tutorId);
            if (!empty($row['report']['document_code'])) {
                $this->documents->revoke((string) $row['report']['document_code']);   // the re-issued report replaces it
            }
            $code = $this->documents->issue('term_report', 'user', (int) $s['user_id'], $data + ['term_id' => $termId], $tutorId);
            $this->grades->setReportDocument($reportId, $code);
            $this->notifications->notify([(int) $s['user_id']], 'term_report', 'Your Term ' . $term['number'] . ' report is ready',
                'See your grades and the tutor’s comment.', '/grades');
            $count++;
        }

        return $count;
    }

    /** @return list<array<string, mixed>> */
    public function reportsFor(int $userId): array
    {
        return $this->grades->reportsFor($userId);
    }
}
