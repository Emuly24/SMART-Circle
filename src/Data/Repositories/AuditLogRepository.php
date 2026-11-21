<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

final class AuditLogRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function record(?int $userId, string $action, string $details = '', string $ip = ''): void
    {
        $this->db->run(
            'INSERT INTO audit_log (user_id, action, details, ip) VALUES (?, ?, ?, ?)',
            [$userId, $action, $details, $ip]
        );
    }
}
