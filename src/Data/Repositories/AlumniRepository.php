<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Moving students on at the end of a year, and alumni profiles. */
final class AlumniRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function promote(int $userId, int $form, string $track, ?int $schoolId): void
    {
        $this->db->run(
            "UPDATE student_profiles SET form = ?, track = ?, school_id = COALESCE(?, school_id) WHERE user_id = ? AND status = 'active'",
            [$form, $track, $schoolId, $userId]
        );
    }

    public function graduate(int $userId, int $year): void
    {
        $this->db->transaction(function (Database $db) use ($userId, $year): void {
            $db->run("UPDATE student_profiles SET status = 'graduated' WHERE user_id = ? AND status = 'active'", [$userId]);
            $db->run("UPDATE users SET role = 'alumni' WHERE id = ? AND role = 'student'", [$userId]);
            $db->run('INSERT IGNORE INTO alumni_profiles (user_id, left_year) VALUES (?, ?)', [$userId, $year]);
        });
    }

    /** @return array<string, mixed>|null */
    public function profile(int $userId): ?array
    {
        return $this->db->one(
            'SELECT a.*, u.full_name, u.phone, u.email, sp.track, sp.form FROM alumni_profiles a JOIN users u ON u.id = a.user_id
             LEFT JOIN student_profiles sp ON sp.user_id = a.user_id WHERE a.user_id = ?',
            [$userId]
        );
    }

    /** @param array<string, mixed> $d */
    public function saveProfile(int $userId, array $d): void
    {
        $this->db->run(
            'UPDATE alumni_profiles SET now_doing = ?, institution = ?, programme = ?, needs = ?, can_mentor = ?, mentor_note = ? WHERE user_id = ?',
            [$d['now_doing'], $d['institution'], $d['programme'], $d['needs'], $d['can_mentor'], $d['mentor_note'], $userId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(
            "SELECT a.*, u.full_name, u.phone, u.email, u.last_login_at FROM alumni_profiles a JOIN users u ON u.id = a.user_id
             WHERE u.role = 'alumni' AND u.status = 'active' ORDER BY a.left_year DESC, u.full_name"
        );
    }

    /** @return list<array<string, mixed>> active students with what year-end would do to them */
    public function activeStudents(): array
    {
        return $this->db->all(
            "SELECT u.id, u.full_name, sp.form, sp.track, sp.school_id, s.name AS school_name FROM users u JOIN student_profiles sp ON sp.user_id = u.id
             LEFT JOIN schools s ON s.id = sp.school_id WHERE u.role = 'student' AND u.status = 'active' AND sp.status = 'active' ORDER BY sp.form, u.full_name"
        );
    }

    /** @return list<array<string, mixed>> */
    public function schools(): array
    {
        return $this->db->all('SELECT id, name FROM schools ORDER BY name');
    }
}
