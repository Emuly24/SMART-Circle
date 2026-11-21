<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Student profiles and the one-time account codes the tutor hands out. */
final class StudentRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $p */
    public function createProfile(array $p): void
    {
        $this->db->run(
            'INSERT INTO student_profiles (user_id, application_id, intake_id, form, track, school_id, guardian_name, guardian_phone)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$p['user_id'], $p['application_id'], $p['intake_id'], $p['form'], $p['track'], $p['school_id'], $p['guardian_name'], $p['guardian_phone']]
        );
    }

    /** @return array<string, mixed>|null */
    public function profile(int $userId): ?array
    {
        return $this->db->one('SELECT * FROM student_profiles WHERE user_id = ?', [$userId]);
    }

    public function completeOnboarding(int $userId): void
    {
        $this->db->run(
            'UPDATE student_profiles SET agreement_accepted_at = COALESCE(agreement_accepted_at, NOW()),
             guardian_confirmed_at = COALESCE(guardian_confirmed_at, NOW()) WHERE user_id = ?',
            [$userId]
        );
    }

    /* ---------- Account codes ---------- */

    public function createCode(int $userId, string $purpose, string $codeHash, int $validDays, ?int $createdBy): void
    {
        // A new code replaces any unused older ones.
        $this->db->run('UPDATE account_codes SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
        $this->db->run(
            'INSERT INTO account_codes (user_id, purpose, code_hash, expires_at, created_by) VALUES (?, ?, ?, NOW() + INTERVAL ? DAY, ?)',
            [$userId, $purpose, $codeHash, $validDays, $createdBy]
        );
    }

    /** @return array<string, mixed>|null the newest usable code */
    public function activeCode(int $userId): ?array
    {
        return $this->db->one(
            'SELECT * FROM account_codes WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [$userId]
        );
    }

    public function countCodeAttempt(int $codeId): void
    {
        $this->db->run('UPDATE account_codes SET attempts = attempts + 1 WHERE id = ?', [$codeId]);
    }

    public function useCode(int $codeId): void
    {
        $this->db->run('UPDATE account_codes SET used_at = NOW() WHERE id = ?', [$codeId]);
    }
}
