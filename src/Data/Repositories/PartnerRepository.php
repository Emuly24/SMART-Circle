<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** School partners (teachers) and their profiles. */
final class PartnerRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function createProfile(int $userId, int $schoolId, string $subjects, int $verifiedBy): void
    {
        $this->db->run(
            'INSERT INTO partner_profiles (user_id, school_id, subjects, verified_at, verified_by) VALUES (?, ?, ?, NOW(), ?)',
            [$userId, $schoolId, $subjects, $verifiedBy]
        );
    }

    /** @return array<string, mixed>|null partner with user and school details */
    public function find(int $userId): ?array
    {
        return $this->db->one(
            "SELECT u.id, u.full_name, u.phone, u.email, u.status, u.last_login_at, u.created_at,
                    pp.school_id, pp.subjects, pp.verified_at, s.name AS school_name, s.district
             FROM users u JOIN partner_profiles pp ON pp.user_id = u.id
             LEFT JOIN schools s ON s.id = pp.school_id
             WHERE u.id = ? AND u.role = 'partner'",
            [$userId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(
            "SELECT u.id, u.full_name, u.phone, u.status, u.last_login_at, pp.school_id, pp.subjects,
                    s.name AS school_name, s.district,
                    (SELECT MAX(c.reported_at) FROM topic_coverage c WHERE c.reported_by = u.id) AS last_report
             FROM users u JOIN partner_profiles pp ON pp.user_id = u.id
             LEFT JOIN schools s ON s.id = pp.school_id
             WHERE u.role = 'partner' ORDER BY s.name, u.full_name"
        );
    }

    public function setStatus(int $userId, string $status): void
    {
        $this->db->run("UPDATE users SET status = ? WHERE id = ? AND role = 'partner'", [$status, $userId]);
    }
}
