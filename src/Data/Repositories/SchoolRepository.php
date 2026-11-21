<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Schools (created by applications and partner invitations). */
final class SchoolRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findOrCreate(string $name, string $district): int
    {
        $id = $this->db->scalar('SELECT id FROM schools WHERE name = ? AND district = ?', [$name, $district]);
        if ($id !== null) {
            return (int) $id;
        }

        return $this->db->insert('INSERT INTO schools (name, district) VALUES (?, ?)', [$name, $district]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM schools WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> every school with its students and partners */
    public function allWithCounts(): array
    {
        return $this->db->all(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM student_profiles sp WHERE sp.school_id = s.id AND sp.status = 'active') AS students,
                    (SELECT COUNT(*) FROM partner_profiles pp JOIN users u ON u.id = pp.user_id
                      WHERE pp.school_id = s.id AND u.status = 'active') AS partners,
                    (SELECT COUNT(*) FROM topic_coverage c WHERE c.school_id = s.id) AS coverage_rows,
                    (SELECT MAX(c.reported_at) FROM topic_coverage c WHERE c.school_id = s.id) AS coverage_updated
             FROM schools s ORDER BY s.name"
        );
    }

    /** @return array<int, int> form => active students from this school */
    public function studentsByForm(int $schoolId): array
    {
        $out = [];
        foreach ($this->db->all(
            "SELECT form, COUNT(*) AS n FROM student_profiles WHERE school_id = ? AND status = 'active' GROUP BY form",
            [$schoolId]
        ) as $r) {
            $out[(int) $r['form']] = (int) $r['n'];
        }

        return $out;
    }

    /**
     * Topics this school's students asked for (requests + "me too"), without names.
     * @param list<string> $subjects
     * @return list<array<string, mixed>>
     */
    public function topRequests(int $schoolId, array $subjects, int $limit = 10): array
    {
        if ($subjects === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($subjects), '?'));

        return $this->db->all(
            "SELECT t.id, t.form, t.subject, t.title, p.title AS parent_title, COUNT(*) AS students
             FROM (
                SELECT r.topic_id, r.user_id FROM topic_requests r
                UNION
                SELECT r.topic_id, v.user_id FROM topic_request_votes v JOIN topic_requests r ON r.id = v.request_id
             ) x
             JOIN student_profiles sp ON sp.user_id = x.user_id AND sp.school_id = ?
             JOIN topics t ON t.id = x.topic_id
             LEFT JOIN topics p ON p.id = t.parent_id
             WHERE t.subject IN ($in)
             GROUP BY t.id, t.form, t.subject, t.title, p.title
             ORDER BY students DESC, t.form, t.sort_order
             LIMIT " . max(1, min(50, $limit)),
            [$schoolId, ...$subjects]
        );
    }
}
