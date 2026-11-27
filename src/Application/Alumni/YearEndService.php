<?php

declare(strict_types=1);

namespace SmartCircle\Application\Alumni;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\AlumniRepository;
use SmartCircle\Data\Repositories\AuditLogRepository;

/** End of the school year: move each student up a form, or into the alumni community after Form 4. */
final class YearEndService
{
    public const ACTIONS = ['stay' => 'Stays in the same form', 'promote' => 'Moves up a form', 'graduate' => 'Finished Form 4: joins the alumni'];

    public function __construct(
        private readonly AlumniRepository $alumni,
        private readonly NotificationService $notifications,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function students(): array
    {
        return $this->alumni->activeStudents();
    }

    /** @return list<array<string, mixed>> */
    public function schools(): array
    {
        return $this->alumni->schools();
    }

    /** The suggested action for a form. Pure. */
    public static function suggested(int $form): string
    {
        return $form >= 4 ? 'graduate' : 'promote';
    }

    /**
     * Validates one student's year-end choice. Pure.
     * @param array<string, mixed> $s student (form, track)
     * @param array<string, mixed> $in action, track, school_id
     * @return array{error: string|null, form: int, track: string}
     */
    public static function decide(array $s, array $in): array
    {
        $form = (int) $s['form'];
        $action = (string) ($in['action'] ?? 'stay');
        $track = (string) $s['track'];
        if ($action === 'graduate') {
            return ['error' => $form === 4 ? null : 'Only Form 4 students join the alumni.', 'form' => $form, 'track' => $track];
        }
        if ($action !== 'promote') {
            return ['error' => null, 'form' => $form, 'track' => $track];
        }
        if ($form >= 4) {
            return ['error' => 'Form 4 students finish rather than move up.', 'form' => $form, 'track' => $track];
        }
        $next = $form + 1;
        if ($next === 3) {
            $track = (string) ($in['track'] ?? '');
            if (!in_array($track, ['science', 'humanities'], true)) {
                return ['error' => 'Choose science (Physics and Chemistry) or humanities for Form 3.', 'form' => $form, 'track' => (string) $s['track']];
            }
        }

        return ['error' => null, 'form' => $next, 'track' => $next <= 2 ? 'general' : $track];
    }

    /**
     * Applies the tutor's choices for every student.
     * @param array<string, array<string, mixed>> $choices user id => action, track, school_id
     * @return array{errors: array<int, string>, promoted: int, graduated: int}
     */
    public function apply(array $choices, int $tutorId, string $ip): array
    {
        $errors = [];
        $plan = [];
        foreach ($this->alumni->activeStudents() as $s) {
            $in = $choices[(string) $s['id']] ?? ['action' => 'stay'];
            $d = self::decide($s, $in);
            if ($d['error'] !== null) {
                $errors[(int) $s['id']] = $d['error'];
            }
            $plan[] = [$s, (string) ($in['action'] ?? 'stay'), $d, (int) ($in['school_id'] ?? 0)];
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'promoted' => 0, 'graduated' => 0];
        }
        $promoted = $graduated = 0;
        foreach ($plan as [$s, $action, $d, $schoolId]) {
            $id = (int) $s['id'];
            if ($action === 'promote') {
                $this->alumni->promote($id, $d['form'], $d['track'], $schoolId > 0 ? $schoolId : null);
                $this->notifications->notify([$id], 'year_end', 'Welcome to Form ' . $d['form'] . '!', 'Your topics and lessons now follow the Form ' . $d['form'] . ' syllabus.', '/topics');
                $promoted++;
            } elseif ($action === 'graduate') {
                $this->alumni->graduate($id, (int) date('Y'));
                $this->notifications->notify([$id], 'year_end', 'Welcome to the SMART Circle alumni', 'Tell us what you are doing next, share your MANEB results and stay in touch.', '/alumni');
                $graduated++;
            }
        }
        $this->audit->record($tutorId, 'year_end', 'Promoted ' . $promoted . ', moved to alumni ' . $graduated, $ip);

        return ['errors' => [], 'promoted' => $promoted, 'graduated' => $graduated];
    }
}
