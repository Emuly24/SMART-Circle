<?php

declare(strict_types=1);

namespace SmartCircle\Models;

use PDO;
use PDOException;
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

    public function findFullnameById(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT fullname FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? (string) $row['fullname'] : null;
    }

    public function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);

        return $stmt->fetch() !== false;
    }

    public function phoneExists(string $phone): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);

        return $stmt->fetch() !== false;
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

    /** @param array{username: string, fullname: string, phone: string, email: string, school: string, password: string} $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, fullname, phone, email, school, password, approved)
             VALUES (?, ?, ?, ?, ?, ?, 0)'
        );
        $stmt->execute([
            $data['username'],
            $data['fullname'],
            $data['phone'],
            $data['email'],
            $data['school'],
            $data['password'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public static function extractFirstName(?string $fullname): string
    {
        if ($fullname === null || trim($fullname) === '') {
            return 'User';
        }

        $parts = explode(' ', trim($fullname));

        return $parts[0] !== '' ? $parts[0] : 'User';
    }
}
