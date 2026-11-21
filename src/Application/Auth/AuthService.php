<?php

declare(strict_types=1);

namespace SmartCircle\Application\Auth;

use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\LoginAttemptRepository;
use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Support\PhoneNumber;

/**
 * Login rules: phone + password, with lockout after repeated failures.
 * Knows nothing about HTTP or sessions; the controller handles those.
 */
final class AuthService
{
    // Verified against when the phone is unknown, so response time does not reveal which numbers exist.
    private const DUMMY_HASH = '$2y$10$r9Ci4/Qn.ZIzIkGpT28z5ekQ6sjVOnoAM8lLwZLxzJSd9iSKxzqBu';

    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginAttemptRepository $attempts,
        private readonly AuditLogRepository $audit,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
    ) {
    }

    public function attempt(string $phoneInput, string $password, string $ip): LoginResult
    {
        $phone = PhoneNumber::normalize($phoneInput);
        if ($phone === null || $password === '') {
            return LoginResult::failed('Enter your phone number and password.');
        }

        if ($this->attempts->recentFailures($phone, $ip, $this->windowSeconds) >= $this->maxAttempts) {
            $minutes = (int) ceil($this->windowSeconds / 60);
            return LoginResult::failed("Too many attempts. Please wait $minutes minutes and try again.");
        }

        $user = $this->users->findForLogin($phone);
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);

        if ($user === null || !$valid) {
            $this->attempts->record($phone, $ip, false);
            return LoginResult::failed('Phone number or password is incorrect.');
        }

        if ($user['status'] !== 'active') {
            return LoginResult::failed('This account is not active. Please contact the tutor.');
        }

        $userId = (int) $user['id'];
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash($userId, password_hash($password, PASSWORD_DEFAULT));
        }

        $this->attempts->record($phone, $ip, true);
        $this->attempts->clearFailures($phone);
        $this->users->recordLogin($userId);
        $this->audit->record($userId, 'login', '', $ip);

        return LoginResult::succeeded($userId, (string) $user['role'], (bool) $user['must_change_password']);
    }

    /** @return array<string, mixed>|null the user, if the account still exists and is active */
    public function activeUser(int $userId): ?array
    {
        $user = $this->users->findById($userId);

        return $user !== null && $user['status'] === 'active' ? $user : null;
    }

    public function recordLogout(int $userId, string $ip): void
    {
        $this->audit->record($userId, 'logout', '', $ip);
    }
}
