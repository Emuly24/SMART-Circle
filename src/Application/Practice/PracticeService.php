<?php

declare(strict_types=1);

namespace SmartCircle\Application\Practice;

use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\PracticeRepository;

/**
 * "Try as": the admin enters practice accounts (students in each form, a partner teacher, an alumnus,
 * a team tutor) at a sample school, to see and test every page exactly as that person would.
 * Practice accounts are marked, cannot log in with a password, and are wiped by reset().
 */
final class PracticeService
{
    /** key => [role, name, phone, gender, form, track] */
    public const ACCOUNTS = [
        'student1' => ['student', 'Practice Student (Form 1)', '+265100000001', 'female', 1, 'general'],
        'student2' => ['student', 'Practice Student (Form 2)', '+265100000002', 'male', 2, 'general'],
        'student3' => ['student', 'Practice Student (Form 3 science)', '+265100000003', 'female', 3, 'science'],
        'student4' => ['student', 'Practice Student (Form 4 humanities)', '+265100000004', 'male', 4, 'humanities'],
        'partner' => ['partner', 'Practice Teacher', '+265100000005', 'female', null, null],
        'alumni' => ['alumni', 'Practice Alumnus', '+265100000006', 'male', 4, 'science'],
        'team' => ['team', 'Practice Team Tutor (Maths, Physics)', '+265100000007', 'female', null, null],
    ];

    public const LABELS = [
        'student1' => 'Student · Form 1',
        'student2' => 'Student · Form 2',
        'student3' => 'Student · Form 3 science',
        'student4' => 'Student · Form 4 humanities',
        'partner' => 'Partner teacher',
        'alumni' => 'Alumnus',
        'team' => 'Team tutor (Maths, Physics)',
    ];

    public function __construct(
        private readonly PracticeRepository $practice,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /** The user id of a practice account, creating the sample school and accounts the first time. */
    public function account(string $key, int $adminId): ?int
    {
        if (!isset(self::ACCOUNTS[$key])) {
            return null;
        }
        if ($this->practice->count() === 0) {
            $this->seed($adminId);
        }

        return $this->practice->userByPhone(self::ACCOUNTS[$key][2]);
    }

    private function seed(int $adminId): void
    {
        $school = $this->practice->createSchool('Practice Secondary School');
        $partnerId = null;
        foreach (self::ACCOUNTS as [$role, $name, $phone, $gender, $form, $track]) {
            $id = $this->practice->createUser($role === 'alumni' ? 'alumni' : $role, $name, $phone, $gender);
            match ($role) {
                'student' => $this->practice->studentProfile($id, (int) $form, (string) $track, $school),
                'alumni' => [$this->practice->studentProfile($id, (int) $form, (string) $track, $school), $this->practice->graduate($id)],
                'partner' => [$this->practice->partnerProfile($id, $school, $adminId), $partnerId = $id],
                'team' => $this->practice->teamProfile($id, $adminId),
                default => null,
            };
        }
        $this->practice->coverage($school, $partnerId ?? $adminId);
    }

    public function exists(): bool
    {
        return $this->practice->count() > 0;
    }

    public function reset(int $adminId, string $ip): void
    {
        $this->practice->reset();
        $this->audit->record($adminId, 'practice_reset', 'Practice accounts and their data removed', $ip);
    }
}
