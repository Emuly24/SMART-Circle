<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

use SmartCircle\Data\Database;
use SmartCircle\Data\Repositories\ApplicationRepository;
use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\IntakeRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Support\Codes;

/**
 * The tutor's side of admissions: review, decide, create student accounts.
 */
final class ReviewService
{
    public const DECISIONS = ['shortlist' => 'shortlisted', 'admit' => 'admitted', 'waitlist' => 'waitlisted', 'decline' => 'declined'];
    public const CODE_VALID_DAYS = 14;

    public function __construct(
        private readonly Database $db,
        private readonly ApplicationRepository $applications,
        private readonly IntakeRepository $intakes,
        private readonly UserRepository $users,
        private readonly StudentRepository $students,
        private readonly DocumentService $documents,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function currentIntake(): ?array
    {
        return $this->intakes->current();
    }

    public function seatChart(array $intake): SeatChart
    {
        return new SeatChart((int) $intake['seats_per_group'], $this->applications->seatCounts((int) $intake['id']));
    }

    /**
     * @param array{status?: string, form?: int} $filter
     * @return list<array<string, mixed>>
     */
    public function applications(array $intake, array $filter): array
    {
        return $this->applications->forIntake((int) $intake['id'], $filter);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->applications->find($id);
    }

    /** @return list<array<string, mixed>> */
    public function quizAnswers(int $id): array
    {
        return $this->applications->quizAnswers($id);
    }

    /**
     * Record a decision. Admitting creates the student account and returns its one-time setup code.
     * @return array{status: string, setup_code: ?string, letter_code: ?string}
     */
    public function decide(int $applicationId, string $decision, ?string $note, int $tutorId, string $ip): array
    {
        $status = self::DECISIONS[$decision] ?? throw new AdmissionException('Unknown decision.');
        $app = $this->applications->find($applicationId) ?? throw new AdmissionException('Application not found.');
        $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null;

        if ($app['status'] === 'admitted') {
            throw new AdmissionException('This applicant is already admitted.');
        }

        $result = $this->db->transaction(function () use ($app, $status, $note, $tutorId): array {
            $setupCode = null;
            $userId = null;

            if ($status === 'admitted') {
                $intake = $this->intakes->find((int) $app['intake_id']) ?? throw new AdmissionException('Intake not found.');
                if (!$this->seatChart($intake)->hasSeat((int) $app['form'], (string) $app['gender'])) {
                    $who = $app['gender'] === 'female' ? 'girls' : 'boys';
                    throw new AdmissionException("All Form {$app['form']} seats for {$who} are taken. Put this applicant on the waiting list instead.");
                }
                if ($this->users->findByPhone((string) $app['phone']) !== null) {
                    throw new AdmissionException('An account with this phone number already exists.');
                }
                // The account starts with an unusable random password; the student sets their own with the setup code.
                $userId = $this->users->create('student', (string) $app['full_name'], (string) $app['phone'], password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $app['email'] ?: null, (string) $app['gender']);
                $this->students->createProfile([
                    'user_id' => $userId,
                    'application_id' => (int) $app['id'],
                    'intake_id' => (int) $app['intake_id'],
                    'form' => (int) $app['form'],
                    'track' => (string) $app['track'],
                    'school_id' => $app['school_id'] !== null ? (int) $app['school_id'] : null,
                    'guardian_name' => (string) $app['guardian_name'],
                    'guardian_phone' => (string) $app['guardian_phone'],
                ]);
                $setupCode = $this->newCode($userId, 'setup', $tutorId);
            }

            $this->applications->decide((int) $app['id'], $status, $note, $tutorId, $userId);

            $letterType = ['admitted' => 'admission_letter', 'waitlisted' => 'waitlist_letter', 'declined' => 'decline_letter'][$status] ?? null;
            $letterCode = null;
            if ($letterType !== null) {
                $this->documents->revokeFor('application', (int) $app['id']);
                $letterCode = $this->documents->issue($letterType, 'application', (int) $app['id'], [
                    'full_name' => $app['full_name'],
                    'form' => (int) $app['form'],
                    'school' => $app['school_name'],
                    'reference' => $app['reference'],
                    'track' => $app['track'],
                    'help_subjects' => $app['help_subjects'],
                    'guardian_name' => $app['guardian_name'],
                ], $tutorId);
            }

            return ['status' => $status, 'setup_code' => $setupCode, 'letter_code' => $letterCode];
        });

        $this->audit->record($tutorId, 'application_' . $status, (string) $app['reference'], $ip);

        return $result;
    }

    /** New setup code for an admitted student (lost code) or a password reset. */
    public function issueCode(int $userId, string $purpose, int $tutorId): string
    {
        return $this->newCode($userId, $purpose, $tutorId);
    }

    private function newCode(int $userId, string $purpose, int $tutorId): string
    {
        $code = Codes::random(8);
        $this->students->createCode($userId, $purpose, password_hash($code, PASSWORD_DEFAULT), self::CODE_VALID_DAYS, $tutorId);

        return $code;
    }

    public function updateIntake(int $id, string $name, ?string $closesAt, int $seats, bool $active): void
    {
        $this->intakes->update($id, mb_substr(trim($name), 0, 100) ?: 'Intake', $closesAt, max(1, min(10, $seats)), $active);
    }

    public function newIntake(string $name, int $seats): int
    {
        return $this->intakes->create(mb_substr(trim($name), 0, 100) ?: 'New intake', max(1, min(10, $seats)));
    }
}
