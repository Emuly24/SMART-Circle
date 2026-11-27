<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/**
 * Practice accounts and their sample school for the admin's "Try as" test mode.
 * Everything here is marked is_practice and removed by reset().
 */
final class PracticeRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function userByPhone(string $phone): ?int
    {
        $id = $this->db->scalar('SELECT id FROM users WHERE phone = ? AND is_practice = 1', [$phone]);

        return $id === null ? null : (int) $id;
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM users WHERE is_practice = 1');
    }

    public function createSchool(string $name): int
    {
        return $this->db->insert("INSERT INTO schools (name, district, is_practice) VALUES (?, 'Practice', 1)", [$name]);
    }

    public function createUser(string $role, string $name, string $phone, string $gender): int
    {
        return $this->db->insert(
            'INSERT INTO users (role, full_name, phone, gender, password_hash, is_practice) VALUES (?, ?, ?, ?, ?, 1)',
            // Nobody can log in to a practice account with a password; the admin enters it from the dashboard.
            [$role, $name, $phone, $gender, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]
        );
    }

    public function studentProfile(int $userId, int $form, string $track, int $schoolId): void
    {
        $this->db->run(
            "INSERT INTO student_profiles (user_id, form, track, school_id, guardian_name, guardian_phone, study_mode, agreement_accepted_at, guardian_confirmed_at)
             VALUES (?, ?, ?, ?, 'Practice Guardian', '+265100000999', 'day', NOW(), NOW())",
            [$userId, $form, $track, $schoolId]
        );
    }

    public function graduate(int $userId): void
    {
        $this->db->run("UPDATE student_profiles SET status = 'graduated' WHERE user_id = ?", [$userId]);
        $this->db->run("INSERT INTO alumni_profiles (user_id, left_year, now_doing, institution) VALUES (?, YEAR(NOW()) - 1, 'university', 'Practice University')", [$userId]);
    }

    public function partnerProfile(int $userId, int $schoolId, int $adminId): void
    {
        $this->db->run(
            "INSERT INTO partner_profiles (user_id, school_id, subjects, verified_at, verified_by) VALUES (?, ?, 'mathematics,physics,chemistry,biology,english', NOW(), ?)",
            [$userId, $schoolId, $adminId]
        );
    }

    public function teamProfile(int $userId, int $adminId): void
    {
        $this->db->run(
            "INSERT INTO team_profiles (user_id, subjects, status, agreement_version, agreed_at, invited_by) VALUES (?, 'mathematics,physics', 'active', 'practice', NOW(), ?)",
            [$userId, $adminId]
        );
    }

    /** Marks the first few main topics of every form and subject as taught at the practice school. */
    public function coverage(int $schoolId, int $userId): void
    {
        foreach ($this->db->all('SELECT form, subject FROM topics WHERE is_active = 1 AND parent_id IS NULL GROUP BY form, subject') as $g) {
            $ids = $this->db->all(
                'SELECT id FROM topics WHERE is_active = 1 AND parent_id IS NULL AND form = ? AND subject = ? ORDER BY sort_order, id LIMIT 4',
                [$g['form'], $g['subject']]
            );
            foreach ($ids as $i => $t) {
                $this->db->run(
                    "INSERT IGNORE INTO topic_coverage (school_id, topic_id, status, source, reported_by) VALUES (?, ?, ?, 'partner', ?)",
                    [$schoolId, $t['id'], $i < 3 ? 'covered' : 'covering', $userId]
                );
            }
        }
    }

    /** Removes every practice account and school, and everything that belongs to them. */
    public function reset(): void
    {
        $this->db->transaction(function (Database $db): void {
            $db->run('DELETE FROM conversations WHERE created_by IN (SELECT id FROM users WHERE is_practice = 1)
                      OR student_id IN (SELECT id FROM users WHERE is_practice = 1)');
            $db->run('DELETE FROM community_posts WHERE user_id IN (SELECT id FROM users WHERE is_practice = 1)');
            $db->run('DELETE FROM assessments WHERE created_by IN (SELECT id FROM users WHERE is_practice = 1)');
            $db->run('DELETE FROM notes WHERE created_by IN (SELECT id FROM users WHERE is_practice = 1)');
            $db->run('DELETE FROM questions WHERE created_by IN (SELECT id FROM users WHERE is_practice = 1)');
            $db->run('DELETE FROM users WHERE is_practice = 1');
            $db->run('DELETE FROM schools WHERE is_practice = 1');
        });
    }
}
