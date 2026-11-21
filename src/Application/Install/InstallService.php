<?php

declare(strict_types=1);

namespace SmartCircle\Application\Install;

use InvalidArgumentException;
use SmartCircle\Data\Migrator;
use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Support\PhoneNumber;

/**
 * One-time setup from the browser: create the tables and the tutor account.
 * Locked for good once a tutor exists.
 */
final class InstallService
{
    public const MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        private readonly Migrator $migrator,
        private readonly UserRepository $users,
        private readonly AuditLogRepository $audit,
        private readonly string $installToken,
    ) {
    }

    public function isInstalled(): bool
    {
        if (!in_array('0001_core', $this->migrator->applied(), true)) {
            return false;
        }

        return $this->users->countByRole('tutor') > 0;
    }

    public function tokenConfigured(): bool
    {
        return strlen($this->installToken) >= 16;
    }

    /**
     * @return array<string, string> field => error message (empty on success)
     */
    public function validate(string $token, string $fullName, string $phoneInput, string $password, string $confirm): array
    {
        $errors = [];
        if (!$this->tokenConfigured() || !hash_equals($this->installToken, $token)) {
            $errors['token'] = 'The install token does not match the one in secrets.php.';
        }
        if (mb_strlen(trim($fullName)) < 3) {
            $errors['full_name'] = 'Enter your full name.';
        }
        if (PhoneNumber::normalize($phoneInput) === null) {
            $errors['phone'] = 'Enter a valid Malawian phone number, e.g. 0999 123 456.';
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'Use at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }

        return $errors;
    }

    /** @return list<string> database updates shipped with new code but not applied yet */
    public function pendingUpdates(): array
    {
        return $this->migrator->pending();
    }

    /** @return list<string> the updates applied */
    public function applyUpdates(int $userId, string $ip): array
    {
        $applied = $this->migrator->migrate();
        if ($applied !== []) {
            $this->audit->record($userId, 'database_update', implode(', ', $applied), $ip);
        }

        return $applied;
    }

    /** @return int the new tutor's user id */
    public function install(string $fullName, string $phoneInput, string $password, string $ip): int
    {
        if ($this->isInstalled()) {
            throw new InstallLockedException('SMART Circle is already installed.');
        }
        $phone = PhoneNumber::normalize($phoneInput) ?? throw new InvalidArgumentException('Invalid phone number.');

        $this->migrator->migrate();

        $id = $this->users->create('tutor', trim($fullName), $phone, password_hash($password, PASSWORD_DEFAULT));
        $this->audit->record($id, 'install', 'Tutor account created; migrations applied', $ip);

        return $id;
    }
}
