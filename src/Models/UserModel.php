<?php

declare(strict_types=1);

namespace SmartCircle\Models;

use PDO;
use SmartCircle\Data\Database;

/**
 * User data access — all raw SQL for user/auth queries lives here.
 */
final class UserModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, fullname, email, phone, school, role, approved,
                    consent_signed, status, suspension_end
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByLogin(string $login): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, fullname, password, approved, consent_signed, status, suspension_end, role
             FROM users
             WHERE phone = ? OR email = ?
             LIMIT 1'
        );
        $stmt->execute([$login, $login]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function hasApplication(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM applications WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return $row !== false;
    }
}
