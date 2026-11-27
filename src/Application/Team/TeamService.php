<?php

declare(strict_types=1);

namespace SmartCircle\Application\Team;

use SmartCircle\Data\Database;
use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Data\Repositories\TeamRepository;
use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Support\Codes;
use SmartCircle\Support\PhoneNumber;
use SmartCircle\Support\Subjects;

/**
 * The SMART Circle Team: volunteer tutors invited by the admin. They join with a setup code,
 * read and agree to the duties and principles, and then teach and mark only their own subjects.
 */
final class TeamService
{
    private const CODE_VALID_DAYS = 14;

    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly TeamRepository $team,
        private readonly StudentRepository $codes,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /**
     * Validates an invitation. Pure.
     * @param array<string, mixed> $in full_name, phone, email, subjects[]
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validate(array $in): array
    {
        $errors = [];
        $name = trim((string) ($in['full_name'] ?? ''));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
            $errors['full_name'] = 'Give their full name.';
        }
        $phone = PhoneNumber::normalize((string) ($in['phone'] ?? ''));
        if ($phone === null) {
            $errors['phone'] = 'Use a Malawian phone number.';
        }
        $email = trim((string) ($in['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'That email address does not look right.';
        }
        $subjects = self::subjects((array) ($in['subjects'] ?? []));
        if ($subjects === []) {
            $errors['subjects'] = 'Choose at least one subject.';
        }

        return ['errors' => $errors, 'clean' => ['full_name' => $name, 'phone' => $phone, 'email' => $email === '' ? null : $email, 'subjects' => $subjects]];
    }

    /** @param array<int|string, mixed> $list @return list<string> */
    public static function subjects(array $list): array
    {
        return array_values(array_intersect(array_keys(Subjects::NAMES), array_map('strval', $list)));
    }

    /** @return array{errors: array<string, string>, user_id: ?int, code: ?string} */
    public function invite(array $in, int $adminId, string $ip): array
    {
        $v = self::validate($in);
        $c = $v['clean'];
        if ($v['errors'] === [] && $this->users->findByPhone((string) $c['phone']) !== null) {
            $v['errors']['phone'] = 'Someone already has an account with this phone number.';
        }
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'user_id' => null, 'code' => null];
        }
        [$userId, $code] = $this->db->transaction(function () use ($c, $adminId): array {
            $userId = $this->users->create('team', $c['full_name'], (string) $c['phone'], password_hash(Codes::random(24), PASSWORD_DEFAULT), $c['email']);
            $this->team->create($userId, implode(',', $c['subjects']), $adminId);

            return [$userId, $this->newCode($userId, $adminId)];
        });
        $this->audit->record($adminId, 'team_invited', $c['full_name'] . ' (' . implode(', ', $c['subjects']) . ')', $ip);

        return ['errors' => [], 'user_id' => $userId, 'code' => $code];
    }

    public function newCodeFor(int $userId, int $adminId): ?string
    {
        return $this->team->find($userId) === null ? null : $this->newCode($userId, $adminId);
    }

    private function newCode(int $userId, int $adminId): string
    {
        $code = Codes::random(8);
        $this->codes->createCode($userId, 'setup', password_hash($code, PASSWORD_DEFAULT), self::CODE_VALID_DAYS, $adminId);

        return $code;
    }

    /** @return array<string, mixed>|null with 'subject_list' */
    public function find(int $userId): ?array
    {
        $t = $this->team->find($userId);
        if ($t !== null) {
            $t['subject_list'] = self::subjects(explode(',', (string) $t['subjects']));
        }

        return $t;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_map(static fn (array $t): array => $t + ['subject_list' => self::subjects(explode(',', (string) $t['subjects']))], $this->team->all());
    }

    /** The subjects a team tutor may work in; empty until they have agreed. @return list<string> */
    public function scope(int $userId): array
    {
        $t = $this->find($userId);

        return $t !== null && $t['status'] === 'active' ? $t['subject_list'] : [];
    }

    public function needsAgreement(int $userId): bool
    {
        $t = $this->team->find($userId);

        return $t !== null && $t['status'] !== 'active';
    }

    public function agree(int $userId, bool $agreed, string $signedName, string $ip): ?string
    {
        $t = $this->team->find($userId);
        if ($t === null || !in_array($t['status'], ['invited', 'declined'], true)) {
            return 'There is nothing to agree to.';
        }
        if (!$agreed) {
            $this->team->setStatus($userId, 'declined');
            $this->team->setUserStatus($userId, 'disabled');
            $this->audit->record($userId, 'team_declined', TeamAgreement::VERSION, $ip);

            return null;
        }
        if (mb_strtolower(trim($signedName)) !== mb_strtolower(trim((string) $t['full_name']))) {
            return 'Type your full name exactly as shown to agree.';
        }
        $this->team->agree($userId, TeamAgreement::VERSION);
        $this->audit->record($userId, 'team_agreed', TeamAgreement::VERSION, $ip);

        return null;
    }

    /** @return string|null error */
    public function setSubjects(int $userId, array $subjects, int $adminId, string $ip): ?string
    {
        $list = self::subjects($subjects);
        if ($list === [] || $this->team->find($userId) === null) {
            return 'Choose at least one subject.';
        }
        $this->team->setSubjects($userId, implode(',', $list));
        $this->audit->record($adminId, 'team_subjects', $userId . ': ' . implode(', ', $list), $ip);

        return null;
    }

    public function setActive(int $userId, bool $active, int $adminId, string $ip): void
    {
        $t = $this->team->find($userId);
        if ($t === null) {
            return;
        }
        if ($active) {
            $this->team->setStatus($userId, $t['agreed_at'] !== null ? 'active' : 'invited');
            $this->team->setUserStatus($userId, 'active');
        } else {
            $this->team->setStatus($userId, 'removed');
            $this->team->setUserStatus($userId, 'disabled');
        }
        $this->audit->record($adminId, $active ? 'team_restored' : 'team_removed', (string) $userId, $ip);
    }

    /**
     * May a team tutor open an item? Pure: in their subjects, or (for items with no subject yet) their own.
     * @param array{subject: ?string, owner: ?int} $item
     * @param list<string> $scope
     */
    public static function mayOpen(array $item, array $scope, int $userId): bool
    {
        return $item['subject'] !== null ? in_array($item['subject'], $scope, true) : $item['owner'] === $userId;
    }

    /** @return array{subject: ?string, owner: ?int}|null */
    public function item(string $type, int|string $id): ?array
    {
        return $this->team->subjectOf($type, $id);
    }
}
