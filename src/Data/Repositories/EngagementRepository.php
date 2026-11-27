<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Seat activity checks and the figures behind the weekly digest. */
final class EngagementRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Every active admitted student with when they were last seen doing anything, and their latest check.
     * @return list<array<string, mixed>>
     */
    public function seats(): array
    {
        return $this->db->all(
            "SELECT u.id, u.full_name, u.phone, u.email, u.gender, u.last_login_at, sp.form, sp.created_at AS admitted_at,
                    s.name AS school_name, sp.guardian_name, sp.guardian_phone,
                    GREATEST(COALESCE(u.last_login_at, '1970-01-01'),
                             COALESCE((SELECT MAX(v.last_watched_at) FROM lesson_views v WHERE v.user_id = u.id), '1970-01-01'),
                             COALESCE((SELECT MAX(x.submitted_at) FROM submissions x WHERE x.user_id = u.id), '1970-01-01')) AS last_seen_at,
                    c.action AS last_check, c.created_at AS last_check_at, c.until AS paused_until, c.note AS last_check_note
             FROM users u JOIN student_profiles sp ON sp.user_id = u.id
             LEFT JOIN schools s ON s.id = sp.school_id
             LEFT JOIN seat_checks c ON c.id = (SELECT MAX(c2.id) FROM seat_checks c2 WHERE c2.user_id = u.id)
             WHERE u.role = 'student' AND u.status = 'active' AND u.is_practice = 0 AND sp.status = 'active'
             ORDER BY sp.form, u.full_name"
        );
    }

    public function addCheck(int $userId, string $action, ?string $note, ?string $until, ?int $by): void
    {
        $this->db->run('INSERT INTO seat_checks (user_id, action, note, until, created_by) VALUES (?, ?, ?, ?, ?)', [$userId, $action, $note, $until, $by]);
    }

    /** @return list<array<string, mixed>> */
    public function checks(int $userId): array
    {
        return $this->db->all(
            'SELECT c.*, u.full_name AS by_name FROM seat_checks c LEFT JOIN users u ON u.id = c.created_by WHERE c.user_id = ? ORDER BY c.id DESC',
            [$userId]
        );
    }

    /** Frees the seat: the student leaves, the account is switched off, the application is marked withdrawn. */
    public function release(int $userId): void
    {
        $this->db->transaction(function (Database $db) use ($userId): void {
            $db->run("UPDATE student_profiles SET status = 'left' WHERE user_id = ?", [$userId]);
            $db->run("UPDATE users SET status = 'disabled' WHERE id = ? AND role = 'student'", [$userId]);
            $db->run("UPDATE applications SET status = 'withdrawn' WHERE user_id = ? AND status = 'admitted'", [$userId]);
        });
    }

    /** @return array<string, mixed>|null the current intake's waiting list for a form and gender */
    public function waiting(int $form, string $gender): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM applications a JOIN intakes i ON i.id = a.intake_id
             WHERE a.status = 'waitlisted' AND a.form = ? AND a.gender = ? AND i.id = (SELECT MAX(id) FROM intakes)",
            [$form, $gender]
        );
    }

    /* ---------- Weekly digest ---------- */

    /** @return list<array<string, mixed>> lessons and notes first published in the last 7 days */
    public function newThisWeek(): array
    {
        return $this->db->all(
            "SELECT 'lesson' AS kind, id, title, form, subject, topic_id FROM lessons WHERE status = 'published' AND published_at > NOW() - INTERVAL 7 DAY
             UNION ALL
             SELECT 'notes' AS kind, id, title, form, subject, topic_id FROM notes WHERE status = 'published' AND published_at > NOW() - INTERVAL 7 DAY"
        );
    }

    /** @return list<array<string, mixed>> exams and assignments for a form closing in the next 7 days that the student has not handed in */
    public function dueSoon(int $userId, int $form): array
    {
        return $this->db->all(
            "SELECT a.id, a.title, a.subject, a.closes_at FROM assessments a
             WHERE a.status = 'published' AND a.form = ? AND a.note_id IS NULL AND a.closes_at BETWEEN NOW() AND NOW() + INTERVAL 7 DAY
               AND NOT EXISTS (SELECT 1 FROM submissions x WHERE x.assessment_id = a.id AND x.user_id = ? AND x.status <> 'in_progress')
             ORDER BY a.closes_at",
            [$form, $userId]
        );
    }

    public function markedThisWeek(int $userId): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM submissions WHERE user_id = ? AND status = 'marked' AND marked_at > NOW() - INTERVAL 7 DAY", [$userId]);
    }

    public function activeThisWeek(int $userId): int
    {
        return (int) $this->db->scalar(
            "SELECT (SELECT COUNT(*) FROM lesson_views WHERE user_id = ? AND last_watched_at > NOW() - INTERVAL 7 DAY)
                  + (SELECT COUNT(*) FROM submissions WHERE user_id = ? AND submitted_at > NOW() - INTERVAL 7 DAY)",
            [$userId, $userId]
        );
    }

    /** @return array<string, int> what is waiting for the tutor */
    public function tutorWaiting(): array
    {
        return [
            'applications' => (int) $this->db->scalar("SELECT COUNT(*) FROM applications WHERE status IN ('submitted', 'shortlisted')"),
            'to_mark' => (int) $this->db->scalar("SELECT COUNT(*) FROM submissions WHERE status = 'submitted'"),
            'rechecks' => (int) $this->db->scalar("SELECT COUNT(*) FROM recheck_requests WHERE status = 'open'"),
            'guardians' => (int) $this->db->scalar("SELECT COUNT(*) FROM student_profiles sp JOIN users u ON u.id = sp.user_id WHERE sp.status = 'active' AND u.is_practice = 0 AND sp.guardian_confirmed_at IS NULL"),
            'requests' => (int) $this->db->scalar("SELECT COUNT(*) FROM topic_requests WHERE status = 'open'"),
        ];
    }
}
