<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

final class UserRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->one(
            'SELECT id, role, full_name, phone, email, avatar_at, gender, status, is_practice, must_change_password, last_login_at, created_at
             FROM users WHERE id = ?',
            [$id]
        );
    }

    /** @return array<string, mixed>|null includes password_hash, for login only */
    public function findForLogin(string $phone): ?array
    {
        return $this->db->one(
            'SELECT id, role, full_name, phone, status, password_hash, must_change_password FROM users WHERE phone = ?',
            [$phone]
        );
    }

    public function create(string $role, string $fullName, string $phone, string $passwordHash, ?string $email = null, ?string $gender = null): int
    {
        return $this->db->insert(
            'INSERT INTO users (role, full_name, phone, email, gender, password_hash) VALUES (?, ?, ?, ?, ?, ?)',
            [$role, $fullName, $phone, $email, $gender, $passwordHash]
        );
    }

    /** @return array<string, mixed>|null */
    public function findByPhone(string $phone): ?array
    {
        return $this->db->one('SELECT id, role, full_name, phone, status FROM users WHERE phone = ?', [$phone]);
    }

    /** Set a new password and clear any "must change" flag. */
    public function setPassword(int $id, string $hash): void
    {
        $this->db->run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [$hash, $id]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->db->run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    public function recordLogin(int $id): void
    {
        $this->db->run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public function countByRole(string $role): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM users WHERE role = ?', [$role]);
    }

    /** @return list<int> active users with a role (e.g. every tutor, for notifications) */
    public function idsByRole(string $role): array
    {
        return array_map('intval', array_column($this->db->all("SELECT id FROM users WHERE role = ? AND status = 'active'", [$role]), 'id'));
    }

    public function setAvatar(int $userId, bool $has): void
    {
        $this->db->run('UPDATE users SET avatar_at = ' . ($has ? 'NOW()' : 'NULL') . ' WHERE id = ?', [$userId]);
    }

    /** When a member's photo was saved, or null if they have none. */
    public function avatarAt(int $userId): ?string
    {
        $v = $this->db->scalar('SELECT avatar_at FROM users WHERE id = ?', [$userId]);

        return $v === null ? null : (string) $v;
    }
}
