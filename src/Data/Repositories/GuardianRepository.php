<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Guardian contact details and confirmations, kept on the student's profile. */
final class GuardianRepository
{
    private const SELECT = "SELECT sp.user_id, sp.form, sp.guardian_name, sp.guardian_relationship, sp.guardian_phone, sp.guardian_whatsapp,
                sp.guardian_email, sp.guardian_language, sp.guardian_token, sp.guardian_confirmed_at, sp.guardian_confirmed_via,
                sp.guardian_declined_at, u.full_name, s.name AS school_name
         FROM student_profiles sp JOIN users u ON u.id = sp.user_id LEFT JOIN schools s ON s.id = sp.school_id";

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(self::SELECT . " WHERE sp.status = 'active' AND u.status = 'active' AND u.role = 'student' AND u.is_practice = 0
             ORDER BY sp.guardian_declined_at IS NULL, sp.guardian_confirmed_at IS NOT NULL, sp.form, u.full_name");
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId): ?array
    {
        return $this->db->one(self::SELECT . ' WHERE sp.user_id = ?', [$userId]);
    }

    /** @return array<string, mixed>|null */
    public function byToken(string $token): ?array
    {
        return $this->db->one(self::SELECT . " WHERE sp.guardian_token = ? AND sp.status = 'active'", [$token]);
    }

    public function setToken(int $userId, string $token): void
    {
        $this->db->run('UPDATE student_profiles SET guardian_token = ? WHERE user_id = ?', [$token, $userId]);
    }

    public function confirm(int $userId, string $via): void
    {
        $this->db->run('UPDATE student_profiles SET guardian_confirmed_at = NOW(), guardian_confirmed_via = ?, guardian_declined_at = NULL WHERE user_id = ?', [$via, $userId]);
    }

    public function decline(int $userId): void
    {
        $this->db->run('UPDATE student_profiles SET guardian_declined_at = NOW(), guardian_confirmed_at = NULL, guardian_confirmed_via = NULL WHERE user_id = ?', [$userId]);
    }

    /** @param array<string, ?string> $d */
    public function update(int $userId, array $d): void
    {
        $old = $this->find($userId);
        $this->db->run(
            'UPDATE student_profiles SET guardian_name = ?, guardian_relationship = ?, guardian_phone = ?, guardian_whatsapp = ?, guardian_email = ?, guardian_language = ? WHERE user_id = ?',
            [$d['guardian_name'], $d['guardian_relationship'], $d['guardian_phone'], $d['guardian_whatsapp'], $d['guardian_email'], $d['guardian_language'], $userId]
        );
        if ($old !== null && $old['guardian_phone'] !== $d['guardian_phone']) {
            // A new guardian number: they need to confirm again, with a fresh link.
            $this->db->run('UPDATE student_profiles SET guardian_declined_at = NULL, guardian_confirmed_at = NULL, guardian_confirmed_via = NULL, guardian_token = ? WHERE user_id = ?',
                [bin2hex(random_bytes(12)), $userId]);
        }
    }

    /** @return array{0: int, 1: int} lessons watched and work handed in between two moments */
    public function monthStats(int $userId, string $from, string $until): array
    {
        $lessons = (int) $this->db->scalar('SELECT COUNT(*) FROM lesson_views WHERE user_id = ? AND last_watched_at >= ? AND last_watched_at < ?', [$userId, $from, $until]);
        $work = (int) $this->db->scalar("SELECT COUNT(*) FROM submissions WHERE user_id = ? AND status <> 'in_progress' AND submitted_at >= ? AND submitted_at < ?", [$userId, $from, $until]);

        return [$lessons, $work];
    }

    /** @return array<int, string> user id => when this month's update was logged */
    public function monthlySent(string $since): array
    {
        $out = [];
        foreach ($this->db->all(
            "SELECT person_id, MAX(created_at) AS at FROM contact_log WHERE person_type = 'user' AND summary LIKE '[Guardian] Monthly update%' AND created_at >= ? GROUP BY person_id",
            [$since]
        ) as $r) {
            $out[(int) $r['person_id']] = (string) $r['at'];
        }

        return $out;
    }
}
