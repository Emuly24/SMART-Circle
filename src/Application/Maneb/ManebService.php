<?php

declare(strict_types=1);

namespace SmartCircle\Application\Maneb;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Data\Repositories\ManebRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Support\Subjects;

/**
 * MANEB: the revision plan, and results students choose to share.
 * JCE (Form 2) uses letters A-F (A-D pass); MSCE (Form 4) uses 1-9 (1-2 distinction, 3-6 credit, 7-8 pass, 9 not a pass).
 */
final class ManebService
{
    public const EXAMS = ['jce' => 'JCE (Form 2)', 'msce' => 'MSCE (Form 4)'];
    public const GRADES = ['jce' => ['A', 'B', 'C', 'D', 'F'], 'msce' => ['1', '2', '3', '4', '5', '6', '7', '8', '9']];
    public const SHARE = [
        'private' => 'Only me and the tutor',
        'summary' => 'Count my grades in SMART Circle’s results summary (no name)',
        'named' => 'Summary, and show my name and advice on the results page',
    ];

    public function __construct(
        private readonly ManebRepository $maneb,
        private readonly StudentRepository $students,
        private readonly AccessService $access,
        private readonly NotificationService $notifications,
    ) {
    }

    /* ---------- Revision ---------- */

    /**
     * The revision plan: the window, what is open per subject, and a days-left pacing hint.
     * @return array<string, mixed>|null null when the student is not in a revision window
     */
    public function plan(int $userId): ?array
    {
        $profile = $this->access->withRevision($this->students->profile($userId));
        if ($profile === null || $profile['revision'] === null) {
            return null;
        }
        $w = $profile['revision'];
        $subjects = Subjects::forTrack((string) $profile['track']);
        $rows = array_values(array_filter(
            $this->maneb->revisionCounts($userId, $w['forms']),
            static fn (array $r): bool => in_array($r['subject'], $subjects, true)
        ));

        return ['window' => $w, 'rows' => $rows, 'profile' => $profile] + self::pacing($rows, (int) $w['days']);
    }

    /**
     * How many unwatched lessons a day would cover everything before the exam. Pure.
     * @param list<array<string, mixed>> $rows
     * @return array{left: int, per_day: int}
     */
    public static function pacing(array $rows, int $days): array
    {
        $left = 0;
        foreach ($rows as $r) {
            $left += max(0, (int) $r['lessons'] - (int) $r['watched']);
        }

        return ['left' => $left, 'per_day' => $left === 0 ? 0 : (int) ceil($left / max(1, $days))];
    }

    /** @return list<array<string, mixed>> active students in a revision window now, with the exam */
    public function inRevision(): array
    {
        $out = [];
        foreach ($this->access->allStudents() as $s) {
            $w = $this->access->revision((int) $s['form']);
            if ($w !== null) {
                $out[] = $s + ['window' => $w];
            }
        }

        return $out;
    }

    /* ---------- Results ---------- */

    /**
     * Which exams a person may record results for.
     * @param array<string, mixed>|null $profile student profile (form, status)
     * @return list<string>
     */
    public static function examsFor(string $role, ?array $profile): array
    {
        if ($profile === null) {
            return [];
        }
        if ($role === 'alumni') {
            return ['jce', 'msce'];
        }
        $form = (int) $profile['form'];

        return $role === 'student' ? ($form === 4 ? ['jce', 'msce'] : ($form >= 2 ? ['jce'] : [])) : [];
    }

    /**
     * Validates a results form. Pure.
     * @param array<string, mixed> $in exam, year, grades[subject], subjects_sat, subjects_passed, points, reflection, share
     * @param list<string> $subjects the student's subjects
     * @param list<string> $exams exams they may record
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validate(array $in, array $subjects, array $exams, int $thisYear): array
    {
        $errors = [];
        $exam = (string) ($in['exam'] ?? '');
        if (!in_array($exam, $exams, true)) {
            $errors['exam'] = 'Choose the exam.';
        }
        $year = (int) ($in['year'] ?? 0);
        if ($year < 2015 || $year > $thisYear) {
            $errors['year'] = 'Give the year you wrote the exam.';
        }
        $grades = [];
        $allowed = self::GRADES[$exam] ?? [];
        foreach ($subjects as $s) {
            $g = strtoupper(trim((string) (($in['grades'] ?? [])[$s] ?? '')));
            if ($g === '') {
                continue;
            }
            if (!in_array($g, $allowed, true)) {
                $errors['grades'] = 'Use the grades on the result slip (' . implode(', ', $allowed) . ').';
                continue;
            }
            $grades[$s] = $g;
        }
        if ($grades === [] && !isset($errors['grades'])) {
            $errors['grades'] = 'Enter at least one grade.';
        }
        $num = static function (string $key, int $min, int $max) use ($in, &$errors, $exam): ?int {
            $v = trim((string) ($in[$key] ?? ''));
            if ($v === '') {
                return null;
            }
            if (!ctype_digit($v) || (int) $v < $min || (int) $v > $max) {
                $errors[$key] = 'That number does not look right.';

                return null;
            }

            return (int) $v;
        };
        $sat = $num('subjects_sat', 1, 15);
        $passed = $num('subjects_passed', 0, 15);
        if ($sat !== null && $passed !== null && $passed > $sat) {
            $errors['subjects_passed'] = 'Passed cannot be more than the subjects you wrote.';
        }
        $points = $exam === 'msce' ? $num('points', 6, 54) : null;
        $share = (string) ($in['share'] ?? 'private');
        if (!isset(self::SHARE[$share])) {
            $share = 'private';
        }
        $reflection = trim((string) ($in['reflection'] ?? ''));
        if (mb_strlen($reflection) > 1500) {
            $errors['reflection'] = 'Keep it under 1500 characters.';
        }

        return ['errors' => $errors, 'clean' => [
            'exam' => $exam, 'year' => $year, 'grades' => json_encode($grades, JSON_THROW_ON_ERROR),
            'subjects_sat' => $sat, 'subjects_passed' => $passed, 'points' => $points,
            'reflection' => $reflection === '' ? null : $reflection, 'share' => $share,
        ]];
    }

    /** @return array{errors: array<string, string>, id: int|null} */
    public function save(int $userId, string $role, array $in): array
    {
        $profile = $this->students->profile($userId);
        $v = self::validate($in, Subjects::forTrack((string) ($profile['track'] ?? 'general')), self::examsFor($role, $profile), (int) date('Y'));
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'id' => null];
        }
        $id = $this->maneb->saveResult($userId, $v['clean']);
        $this->notifications->notifyTutors('maneb_result', 'MANEB results shared', 'A student has recorded their ' . strtoupper($v['clean']['exam']) . ' results.', '/studio/results');

        return ['errors' => [], 'id' => $id];
    }

    /** @return list<array<string, mixed>> */
    public function mine(int $userId): array
    {
        return $this->maneb->resultsFor($userId);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->maneb->allResults();
    }

    public function verify(int $id, int $tutorId): bool
    {
        if ($this->maneb->result($id) === null) {
            return false;
        }
        $this->maneb->verify($id, $tutorId);

        return true;
    }

    /** Is a grade a pass? */
    public static function passes(string $exam, string $grade): bool
    {
        return $exam === 'jce' ? in_array($grade, ['A', 'B', 'C', 'D'], true) : in_array($grade, ['1', '2', '3', '4', '5', '6', '7', '8'], true);
    }

    /**
     * The public summary: per exam and year, how many students shared, and per subject the grades
     * and pass rate. Names only for those who chose "named" and whose slip the tutor saw. Pure.
     * @param list<array<string, mixed>> $rows from ManebRepository::shared()
     * @return list<array<string, mixed>>
     */
    public static function summary(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $key = $r['exam'] . ':' . $r['year'];
            $g = &$groups[$key];
            $g ??= ['exam' => (string) $r['exam'], 'year' => (int) $r['year'], 'students' => 0, 'verified' => 0, 'subjects' => [], 'named' => []];
            $g['students']++;
            if ($r['verified_at'] !== null) {
                $g['verified']++;
            }
            foreach ((array) json_decode((string) $r['grades'], true) as $subject => $grade) {
                $s = &$g['subjects'][$subject];
                $s ??= ['grades' => [], 'passed' => 0, 'total' => 0];
                $s['grades'][(string) $grade] = ($s['grades'][(string) $grade] ?? 0) + 1;
                $s['total']++;
                if (self::passes((string) $r['exam'], (string) $grade)) {
                    $s['passed']++;
                }
                unset($s);
            }
            if ($r['share'] === 'named' && $r['verified_at'] !== null) {
                $g['named'][] = ['name' => (string) $r['full_name'], 'reflection' => $r['reflection'], 'points' => $r['points']];
            }
            unset($g);
        }
        foreach ($groups as &$g) {
            $order = array_flip(array_keys(Subjects::NAMES));
            uksort($g['subjects'], static fn (string $a, string $b): int => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));
            foreach ($g['subjects'] as &$s) {
                $grades = self::GRADES[$g['exam']];
                uksort($s['grades'], static fn (string $a, string $b): int => array_search($a, $grades, true) <=> array_search($b, $grades, true));
            }
            unset($s);
        }
        unset($g);
        $out = array_values($groups);
        usort($out, static fn (array $a, array $b): int => [$b['year'], $b['exam']] <=> [$a['year'], $a['exam']]);

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function publicSummary(): array
    {
        return self::summary($this->maneb->shared());
    }
}
