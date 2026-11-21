<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Support\Codes;
use SmartCircle\Support\PhoneNumber;

/**
 * First login for admitted students (and password resets): phone + the code the tutor
 * gave them -> choose a password -> class agreement and guardian confirmation.
 * No email or SMS needed.
 */
final class AccountSetupService
{
    public const MIN_PASSWORD = 8;
    private const MAX_CODE_ATTEMPTS = 5;

    public function __construct(
        private readonly UserRepository $users,
        private readonly StudentRepository $students,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /** @return array{user_id: int, code_id: int} */
    public function checkCode(string $phoneInput, string $codeInput): array
    {
        $phone = PhoneNumber::normalize($phoneInput);
        $user = $phone !== null ? $this->users->findByPhone($phone) : null;
        $code = $user !== null ? $this->students->activeCode((int) $user['id']) : null;

        if ($user === null || $code === null) {
            throw new AdmissionException('That phone number and code do not match. Check the code from your tutor.');
        }
        if ((int) $code['attempts'] >= self::MAX_CODE_ATTEMPTS) {
            throw new AdmissionException('Too many wrong tries. Ask your tutor for a new code.');
        }
        if (!password_verify(Codes::normalize($codeInput), (string) $code['code_hash'])) {
            $this->students->countCodeAttempt((int) $code['id']);
            throw new AdmissionException('That phone number and code do not match. Check the code from your tutor.');
        }

        return ['user_id' => (int) $user['id'], 'code_id' => (int) $code['id']];
    }

    /** @return string error message, or '' when the password is set */
    public function setPassword(int $userId, int $codeId, string $password, string $confirm, string $ip): string
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            return 'Use at least ' . self::MIN_PASSWORD . ' characters.';
        }
        if ($password !== $confirm) {
            return 'The two passwords do not match.';
        }
        $this->users->setPassword($userId, password_hash($password, PASSWORD_DEFAULT));
        $this->students->useCode($codeId);
        $this->audit->record($userId, 'password_set', 'via tutor code', $ip);

        return '';
    }

    public function needsOnboarding(int $userId): bool
    {
        $profile = $this->students->profile($userId);

        return $profile !== null && ($profile['agreement_accepted_at'] === null || $profile['guardian_confirmed_at'] === null);
    }

    public function completeOnboarding(int $userId): void
    {
        $this->students->completeOnboarding($userId);
    }

    /** @return array<string, mixed>|null */
    public function profile(int $userId): ?array
    {
        return $this->students->profile($userId);
    }
}
