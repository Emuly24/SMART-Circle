<?php

declare(strict_types=1);

namespace SmartCircle\Data;

/**
 * Example repository — copy this pattern when migrating inline SQL from page scripts.
 *
 * Usage in a future service/controller:
 *   $users = (new UserRepository())->findByLogin($emailOrPhone);
 */
final class UserRepository extends Repository
{
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, phone, role, status FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByLogin(string $login): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, phone, password, role, status
             FROM users
             WHERE email = ? OR phone = ?
             LIMIT 1'
        );
        $stmt->execute([$login, $login]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
