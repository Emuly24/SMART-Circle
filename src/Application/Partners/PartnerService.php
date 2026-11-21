<?php

declare(strict_types=1);

namespace SmartCircle\Application\Partners;

use SmartCircle\Application\Admissions\DocumentService;
use SmartCircle\Data\Database;
use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\PartnerRepository;
use SmartCircle\Data\Repositories\SchoolRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Support\Codes;
use SmartCircle\Support\Malawi;
use SmartCircle\Support\PhoneNumber;
use SmartCircle\Support\Subjects;

/**
 * School partners: teachers the tutor invites. SMART Circle supports schools, never
 * competes with them: partners tell us what their school has taught and which
 * textbooks it uses, and see (without names) where their students are stuck.
 *
 * The tutor creates the account and hands over a one-time setup code (same /welcome
 * flow as students) together with a printable, verifiable invitation letter.
 */
final class PartnerService
{
    private const CODE_VALID_DAYS = 30;

    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly PartnerRepository $partners,
        private readonly SchoolRepository $schools,
        private readonly StudentRepository $codes,
        private readonly DocumentService $documents,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $input full_name, phone, email, school_name, district, subjects (list)
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validate(array $input): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['full_name'] ?? '')));
        $phone = PhoneNumber::normalize((string) ($input['phone'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $school = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['school_name'] ?? '')));
        $district = (string) ($input['district'] ?? '');
        $subjects = Subjects::clean($input['subjects'] ?? []);

        $errors = [];
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150 || !str_contains($name, ' ')) {
            $errors['full_name'] = 'Enter the teacher’s first name and surname.';
        }
        if ($phone === null) {
            $errors['phone'] = 'Enter a Malawi phone number, e.g. 0991 234 567.';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Check the email address, or leave it empty.';
        }
        if (mb_strlen($school) < 3 || mb_strlen($school) > 150) {
            $errors['school_name'] = 'Enter the school’s name.';
        }
        if (!in_array($district, Malawi::DISTRICTS, true)) {
            $errors['district'] = 'Choose the district.';
        }
        if ($subjects === []) {
            $errors['subjects'] = 'Choose at least one subject they teach.';
        }

        return ['errors' => $errors, 'clean' => [
            'full_name' => $name, 'phone' => $phone, 'email' => $email === '' ? null : $email,
            'school_name' => $school, 'district' => $district, 'subjects' => $subjects,
        ]];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, user_id: ?int, code: ?string}
     */
    public function invite(array $input, int $tutorId, string $ip): array
    {
        $v = self::validate($input);
        $c = $v['clean'];
        if ($v['errors'] === [] && $this->users->findByPhone((string) $c['phone']) !== null) {
            $v['errors']['phone'] = 'Someone already has an account with this phone number.';
        }
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'user_id' => null, 'code' => null];
        }

        [$userId, $code] = $this->db->transaction(function () use ($c, $tutorId): array {
            $schoolId = $this->schools->findOrCreate($c['school_name'], $c['district']);
            // Unusable password until the teacher chooses one with the setup code.
            $userId = $this->users->create('partner', $c['full_name'], $c['phone'], password_hash(Codes::random(24), PASSWORD_DEFAULT), $c['email']);
            $this->partners->createProfile($userId, $schoolId, implode(',', $c['subjects']), $tutorId);
            $code = $this->newCode($userId, $tutorId);
            $this->documents->issue('partner_invite', 'user', $userId, [
                'full_name' => $c['full_name'],
                'school' => $c['school_name'],
                'district' => $c['district'],
                'subjects' => implode(',', $c['subjects']),
            ], $tutorId);

            return [$userId, $code];
        });
        $this->audit->record($tutorId, 'partner_invited', $c['full_name'] . ', ' . $c['school_name'], $ip);

        return ['errors' => [], 'user_id' => $userId, 'code' => $code];
    }

    public function issueCode(int $userId, int $tutorId): ?string
    {
        return $this->partners->find($userId) === null ? null : $this->newCode($userId, $tutorId);
    }

    public function setActive(int $userId, bool $active, int $tutorId, string $ip): void
    {
        $this->partners->setStatus($userId, $active ? 'active' : 'disabled');
        $this->audit->record($tutorId, $active ? 'partner_enabled' : 'partner_disabled', (string) $userId, $ip);
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId): ?array
    {
        $p = $this->partners->find($userId);
        if ($p !== null) {
            $p['subject_list'] = array_values(array_filter(explode(',', (string) $p['subjects'])));
        }

        return $p;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->partners->all();
    }

    /** @return list<array<string, mixed>> */
    public function letters(int $userId): array
    {
        return $this->documents->forUser($userId);
    }

    private function newCode(int $userId, int $tutorId): string
    {
        $code = Codes::random(8);
        $this->codes->createCode($userId, 'setup', password_hash($code, PASSWORD_DEFAULT), self::CODE_VALID_DAYS, $tutorId);

        return $code;
    }
}
