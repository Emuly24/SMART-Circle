<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Activity figures for the tutor's student overview, and the contact log. */
final class OverviewRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> user id => activity figures */
    public function activity(): array
    {
        $out = [];
        foreach ($this->db->all(
            "SELECT u.id, u.full_name, u.phone, u.email, u.last_login_at, u.created_at,
                    sp.form, sp.track, sp.school_id, sp.study_mode, sp.guardian_name, sp.guardian_phone, s.name AS school_name,
                    (SELECT COUNT(*) FROM lesson_views v WHERE v.user_id = u.id AND v.last_watched_at > NOW() - INTERVAL 14 DAY) AS lessons_14d,
                    (SELECT COUNT(*) FROM submissions x WHERE x.user_id = u.id AND x.status <> 'in_progress' AND x.submitted_at > NOW() - INTERVAL 30 DAY) AS work_30d,
                    (SELECT MAX(x.submitted_at) FROM submissions x WHERE x.user_id = u.id) AS last_work_at,
                    (SELECT COUNT(*) FROM topic_requests r WHERE r.user_id = u.id AND r.status IN ('open', 'planned')) AS open_requests,
                    (SELECT COUNT(*) FROM assessments a WHERE a.status IN ('published', 'closed') AND a.form = sp.form
                      AND a.closes_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()
                      AND (sp.track <> 'humanities' OR a.subject IS NULL OR a.subject NOT IN ('physics', 'chemistry'))
                      AND NOT EXISTS (SELECT 1 FROM submissions x WHERE x.assessment_id = a.id AND x.user_id = u.id AND x.status <> 'in_progress')) AS missed_30d,
                    (SELECT COUNT(*) FROM messages m WHERE m.user_id = u.id AND m.created_at > NOW() - INTERVAL 30 DAY) AS messages_30d
             FROM users u JOIN student_profiles sp ON sp.user_id = u.id LEFT JOIN schools s ON s.id = sp.school_id
             WHERE u.role = 'student' AND u.status = 'active' AND sp.status = 'active'
             ORDER BY sp.form, u.full_name"
        ) as $r) {
            $out[(int) $r['id']] = $r;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> recent things a student did (lessons, work, requests), newest first */
    public function timeline(int $userId, int $limit = 25): array
    {
        return $this->db->all(
            "(SELECT 'lesson' AS type, l.title AS title, v.last_watched_at AS at FROM lesson_views v JOIN lessons l ON l.id = v.lesson_id WHERE v.user_id = ?)
             UNION ALL
             (SELECT 'work', a.title, x.submitted_at FROM submissions x JOIN assessments a ON a.id = x.assessment_id WHERE x.user_id = ? AND x.submitted_at IS NOT NULL AND x.status <> 'in_progress')
             UNION ALL
             (SELECT 'request', t.title, r.created_at FROM topic_requests r JOIN topics t ON t.id = r.topic_id WHERE r.user_id = ?)
             ORDER BY at DESC LIMIT " . max(1, min(100, $limit)),
            [$userId, $userId, $userId]
        );
    }

    public function logContact(int $userId, string $channel, string $summary, int $tutorId): void
    {
        $this->db->run(
            "INSERT INTO contact_log (person_type, person_id, channel, direction, summary, created_by) VALUES ('user', ?, ?, 'out', ?, ?)",
            [$userId, $channel, $summary, $tutorId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function contacts(int $userId): array
    {
        return $this->db->all(
            "SELECT * FROM contact_log WHERE person_type = 'user' AND person_id = ? ORDER BY created_at DESC, id DESC LIMIT 50",
            [$userId]
        );
    }

    /** @return array<int, string> user id => when the tutor last reached out */
    public function lastContacts(): array
    {
        $out = [];
        foreach ($this->db->all("SELECT person_id, MAX(created_at) AS at FROM contact_log WHERE person_type = 'user' GROUP BY person_id") as $r) {
            $out[(int) $r['person_id']] = (string) $r['at'];
        }

        return $out;
    }
}
