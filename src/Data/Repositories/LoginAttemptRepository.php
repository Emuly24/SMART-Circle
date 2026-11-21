<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

final class LoginAttemptRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function record(string $phone, string $ip, bool $succeeded): void
    {
        $this->db->run(
            'INSERT INTO login_attempts (phone, ip, succeeded) VALUES (?, ?, ?)',
            [$phone, $ip, $succeeded ? 1 : 0]
        );
    }

    /** Failed attempts for this phone OR this IP inside the window. */
    public function recentFailures(string $phone, string $ip, int $windowSeconds): int
    {
        return (int) $this->db->scalar(
            'SELECT GREATEST(
                (SELECT COUNT(*) FROM login_attempts WHERE phone = ? AND succeeded = 0 AND created_at > NOW() - INTERVAL ? SECOND),
                (SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND succeeded = 0 AND created_at > NOW() - INTERVAL ? SECOND)
            )',
            [$phone, $windowSeconds, $ip, $windowSeconds]
        );
    }

    public function clearFailures(string $phone): void
    {
        $this->db->run('DELETE FROM login_attempts WHERE phone = ? AND succeeded = 0', [$phone]);
    }
}
