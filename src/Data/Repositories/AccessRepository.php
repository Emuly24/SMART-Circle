<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/**
 * Everything needed to decide which topics are open to which student:
 * school coverage, students' own reports and early-access exceptions.
 * Loaded in bulk (at most a few hundred rows for 24 students).
 */
final class AccessRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> active students with form, track, school */
    public function students(?int $userId = null): array
    {
        return $this->db->all(
            "SELECT sp.user_id, sp.form, sp.track, sp.school_id, u.full_name
             FROM student_profiles sp JOIN users u ON u.id = sp.user_id
             WHERE sp.status = 'active' AND u.status = 'active'" . ($userId !== null ? ' AND sp.user_id = ?' : ''),
            $userId !== null ? [$userId] : []
        );
    }

    /** @return array<int, int|null> topic id => parent id */
    public function parents(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT id, parent_id FROM topics') as $r) {
            $out[(int) $r['id']] = $r['parent_id'] === null ? null : (int) $r['parent_id'];
        }

        return $out;
    }

    /**
     * @param list<int> $schoolIds
     * @return array<int, array<int, string>> school => topic => status
     */
    public function coverage(array $schoolIds): array
    {
        if ($schoolIds === []) {
            return [];
        }
        $out = [];
        foreach ($this->db->all(
            'SELECT school_id, topic_id, status FROM topic_coverage WHERE school_id IN (' . implode(',', array_fill(0, count($schoolIds), '?')) . ')',
            $schoolIds
        ) as $r) {
            $out[(int) $r['school_id']][(int) $r['topic_id']] = (string) $r['status'];
        }

        return $out;
    }

    /**
     * Subjects someone reliable reports for each school: an active partner's subjects,
     * plus any subject the tutor or a partner has entered coverage for.
     * @param list<int> $schoolIds
     * @return array<int, list<string>>
     */
    public function reportedSubjects(array $schoolIds): array
    {
        if ($schoolIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($schoolIds), '?'));
        $out = [];
        foreach ($this->db->all(
            "SELECT pp.school_id, pp.subjects FROM partner_profiles pp JOIN users u ON u.id = pp.user_id
             WHERE u.status = 'active' AND pp.school_id IN ($in)",
            $schoolIds
        ) as $r) {
            foreach (array_filter(explode(',', (string) $r['subjects'])) as $s) {
                $out[(int) $r['school_id']][$s] = true;
            }
        }
        foreach ($this->db->all(
            "SELECT DISTINCT c.school_id, t.subject FROM topic_coverage c JOIN topics t ON t.id = c.topic_id WHERE c.school_id IN ($in)",
            $schoolIds
        ) as $r) {
            $out[(int) $r['school_id']][(string) $r['subject']] = true;
        }

        return array_map(static fn (array $s): array => array_keys($s), $out);
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array<int, array{status: string, review: ?string}>> user => topic => report
     */
    public function reports(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $out = [];
        foreach ($this->db->all(
            'SELECT user_id, topic_id, status, review FROM student_topic_reports WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')',
            $userIds
        ) as $r) {
            $out[(int) $r['user_id']][(int) $r['topic_id']] = ['status' => (string) $r['status'], 'review' => $r['review']];
        }

        return $out;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array<int, string>> user => topic => pending | approved | declined
     */
    public function exceptions(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $out = [];
        foreach ($this->db->all(
            'SELECT user_id, topic_id, status FROM topic_exceptions WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')',
            $userIds
        ) as $r) {
            $out[(int) $r['user_id']][(int) $r['topic_id']] = (string) $r['status'];
        }

        return $out;
    }

    /* ---------- Writes ---------- */

    public function saveReport(int $userId, int $topicId, string $status): void
    {
        $this->db->run(
            'INSERT INTO student_topic_reports (user_id, topic_id, status) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE review = IF(status = VALUES(status), review, NULL),
                reviewed_by = IF(status = VALUES(status), reviewed_by, NULL),
                reviewed_at = IF(status = VALUES(status), reviewed_at, NULL), status = VALUES(status)',
            [$userId, $topicId, $status]
        );
    }

    public function deleteReport(int $userId, int $topicId): void
    {
        $this->db->run('DELETE FROM student_topic_reports WHERE user_id = ? AND topic_id = ?', [$userId, $topicId]);
    }

    public function reviewReport(int $userId, int $topicId, string $review, int $tutorId): void
    {
        $this->db->run(
            'UPDATE student_topic_reports SET review = ?, reviewed_by = ?, reviewed_at = NOW() WHERE user_id = ? AND topic_id = ?',
            [$review, $tutorId, $userId, $topicId]
        );
    }

    public function requestException(int $userId, int $topicId, string $reason): void
    {
        $this->db->run(
            "INSERT INTO topic_exceptions (user_id, topic_id, reason) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason),
                status = IF(status = 'approved', status, 'pending'),
                decided_by = IF(status = 'approved', decided_by, NULL), decided_at = IF(status = 'approved', decided_at, NULL)",
            [$userId, $topicId, $reason]
        );
    }

    public function decideException(int $userId, int $topicId, string $status, int $tutorId): void
    {
        $this->db->run(
            'UPDATE topic_exceptions SET status = ?, decided_by = ?, decided_at = NOW() WHERE user_id = ? AND topic_id = ?',
            [$status, $tutorId, $userId, $topicId]
        );
    }

    /* ---------- Lists for the tutor ---------- */

    /** @return list<array<string, mixed>> */
    public function pendingExceptions(): array
    {
        return $this->db->all(
            "SELECT x.*, u.full_name, sp.form, s.name AS school_name, t.title AS topic_title, t.subject, t.form AS topic_form, p.title AS parent_title
             FROM topic_exceptions x
             JOIN users u ON u.id = x.user_id
             JOIN student_profiles sp ON sp.user_id = x.user_id
             LEFT JOIN schools s ON s.id = sp.school_id
             JOIN topics t ON t.id = x.topic_id LEFT JOIN topics p ON p.id = t.parent_id
             WHERE x.status = 'pending' ORDER BY x.created_at"
        );
    }

    /** @return list<array<string, mixed>> students' "my class has covered it" reports not yet checked */
    public function uncheckedReports(): array
    {
        return $this->db->all(
            "SELECT r.*, u.full_name, sp.form, sp.school_id, s.name AS school_name, s.district,
                    t.title AS topic_title, t.subject, t.parent_id, p.title AS parent_title
             FROM student_topic_reports r
             JOIN users u ON u.id = r.user_id
             JOIN student_profiles sp ON sp.user_id = r.user_id
             LEFT JOIN schools s ON s.id = sp.school_id
             JOIN topics t ON t.id = r.topic_id LEFT JOIN topics p ON p.id = t.parent_id
             WHERE r.status = 'covered' AND r.review IS NULL ORDER BY r.created_at"
        );
    }

    /** @return list<array<string, mixed>> classmates (same school and form) who disagree about a topic */
    public function disagreements(): array
    {
        return $this->db->all(
            "SELECT s.name AS school_name, sp.form, t.title AS topic_title, t.subject,
                    GROUP_CONCAT(CONCAT(u.full_name, ': ', IF(r.status = 'covered', 'covered', 'not yet')) ORDER BY u.full_name SEPARATOR '; ') AS voices
             FROM student_topic_reports r
             JOIN student_profiles sp ON sp.user_id = r.user_id
             JOIN users u ON u.id = r.user_id
             JOIN schools s ON s.id = sp.school_id
             JOIN topics t ON t.id = r.topic_id
             WHERE r.review IS NULL OR r.review <> 'rejected'
             GROUP BY sp.school_id, s.name, sp.form, r.topic_id, t.title, t.subject
             HAVING COUNT(DISTINCT r.status) > 1"
        );
    }

    /** @return array<string, int> access basis => student lesson views */
    public function viewsByBasis(): array
    {
        $out = [];
        foreach ($this->db->all("SELECT COALESCE(access_basis, 'unknown') AS basis, COUNT(*) AS n FROM lesson_views GROUP BY basis") as $r) {
            $out[(string) $r['basis']] = (int) $r['n'];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function decidedExceptions(): array
    {
        return $this->db->all(
            "SELECT x.*, u.full_name, s.name AS school_name, t.title AS topic_title, t.subject, t.form
             FROM topic_exceptions x JOIN users u ON u.id = x.user_id
             JOIN student_profiles sp ON sp.user_id = x.user_id LEFT JOIN schools s ON s.id = sp.school_id
             JOIN topics t ON t.id = x.topic_id
             WHERE x.status <> 'pending' ORDER BY x.decided_at DESC LIMIT 100"
        );
    }

    /** @return array<string, int> report status/review => count */
    public function reportCounts(): array
    {
        $out = [];
        foreach ($this->db->all(
            "SELECT CONCAT(status, ':', COALESCE(review, 'unchecked')) AS k, COUNT(*) AS n FROM student_topic_reports GROUP BY k"
        ) as $r) {
            $out[(string) $r['k']] = (int) $r['n'];
        }

        return $out;
    }

    public function recordView(int $userId, int $lessonId, string $basis): void
    {
        $this->db->run(
            'INSERT INTO lesson_views (user_id, lesson_id, access_basis) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE access_basis = VALUES(access_basis), last_watched_at = NOW()',
            [$userId, $lessonId, $basis]
        );
    }

    /** @return list<int> published lessons on a topic or its subtopics */
    public function lessonsForTopicTree(int $topicId): array
    {
        return array_map('intval', array_column($this->db->all(
            "SELECT l.id FROM lessons l JOIN topics t ON t.id = l.topic_id
             WHERE l.status = 'published' AND (t.id = ? OR t.parent_id = ?)",
            [$topicId, $topicId]
        ), 'id'));
    }
}
