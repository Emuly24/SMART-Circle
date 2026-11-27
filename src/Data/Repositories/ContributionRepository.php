<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** What partner teachers contribute: calendar confirmations, resources and topic suggestions. */
final class ContributionRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /* ---------- Calendar ---------- */

    public function confirmCalendar(int $schoolId, int $yearId, string $status, ?string $note, int $userId): void
    {
        $this->db->run('INSERT INTO calendar_confirmations (school_id, year_id, status, note, user_id) VALUES (?, ?, ?, ?, ?)',
            [$schoolId, $yearId, $status, $note, $userId]);
    }

    /** @return array<string, mixed>|null the latest answer for a school and year */
    public function calendarStatus(int $schoolId, int $yearId): ?array
    {
        return $this->db->one(
            'SELECT c.*, u.full_name FROM calendar_confirmations c LEFT JOIN users u ON u.id = c.user_id
             WHERE c.school_id = ? AND c.year_id = ? ORDER BY c.id DESC LIMIT 1',
            [$schoolId, $yearId]
        );
    }

    /** @return list<array<string, mixed>> every partner school with its latest answer for a year */
    public function calendarBySchool(int $yearId): array
    {
        return $this->db->all(
            "SELECT s.id AS school_id, s.name AS school_name, c.id, c.status, c.note, c.created_at, c.resolved_at, u.full_name
             FROM schools s
             JOIN (SELECT DISTINCT school_id FROM partner_profiles WHERE school_id IS NOT NULL) p ON p.school_id = s.id
             LEFT JOIN calendar_confirmations c ON c.id = (SELECT MAX(id) FROM calendar_confirmations x WHERE x.school_id = s.id AND x.year_id = ?)
             LEFT JOIN users u ON u.id = c.user_id
             WHERE s.is_practice = 0 ORDER BY c.status = 'problem' DESC, c.id IS NULL DESC, s.name",
            [$yearId]
        );
    }

    public function resolveCalendar(int $id): void
    {
        $this->db->run('UPDATE calendar_confirmations SET resolved_at = NOW() WHERE id = ?', [$id]);
    }

    /* ---------- Resources ---------- */

    /** @param array<string, mixed> $d */
    public function addResource(array $d): int
    {
        return $this->db->insert(
            'INSERT INTO school_resources (school_id, user_id, kind, title, subject, form, via, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['school_id'], $d['user_id'], $d['kind'], $d['title'], $d['subject'], $d['form'], $d['via'], $d['note']]
        );
    }

    public function attachFile(int $id, string $path, string $mime, int $size): void
    {
        $this->db->run('UPDATE school_resources SET file_path = ?, mime = ?, size_bytes = ? WHERE id = ?', [$path, $mime, $size, $id]);
    }

    public function deleteResource(int $id): void
    {
        $this->db->run('DELETE FROM school_resources WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function resource(int $id): ?array
    {
        return $this->db->one(
            'SELECT r.*, u.full_name, s.name AS school_name FROM school_resources r LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN schools s ON s.id = r.school_id WHERE r.id = ?',
            [$id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function resources(?int $schoolId): array
    {
        return $this->db->all(
            'SELECT r.*, u.full_name, s.name AS school_name FROM school_resources r LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN schools s ON s.id = r.school_id'
            . ($schoolId !== null ? ' WHERE r.school_id = ?' : '') . " ORDER BY r.status = 'new' DESC, r.created_at DESC LIMIT 200",
            $schoolId !== null ? [$schoolId] : []
        );
    }

    public function reviewResource(int $id, int $userId): void
    {
        $this->db->run("UPDATE school_resources SET status = 'reviewed', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", [$userId, $id]);
    }

    /* ---------- Topic suggestions ---------- */

    /** @param array<string, mixed> $d */
    public function addSuggestion(array $d): int
    {
        return $this->db->insert(
            'INSERT INTO topic_suggestions (user_id, school_id, form, subject, parent_id, title, note) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$d['user_id'], $d['school_id'], $d['form'], $d['subject'], $d['parent_id'], $d['title'], $d['note']]
        );
    }

    /** @return array<string, mixed>|null */
    public function suggestion(int $id): ?array
    {
        return $this->db->one(
            'SELECT g.*, u.full_name, s.name AS school_name, p.title AS parent_title FROM topic_suggestions g
             LEFT JOIN users u ON u.id = g.user_id LEFT JOIN schools s ON s.id = g.school_id LEFT JOIN topics p ON p.id = g.parent_id WHERE g.id = ?',
            [$id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function suggestions(?int $schoolId): array
    {
        return $this->db->all(
            'SELECT g.*, u.full_name, s.name AS school_name, p.title AS parent_title FROM topic_suggestions g
             LEFT JOIN users u ON u.id = g.user_id LEFT JOIN schools s ON s.id = g.school_id LEFT JOIN topics p ON p.id = g.parent_id'
            . ($schoolId !== null ? ' WHERE g.school_id = ?' : '') . " ORDER BY g.status = 'pending' DESC, g.created_at DESC LIMIT 200",
            $schoolId !== null ? [$schoolId] : []
        );
    }

    public function decide(int $id, string $status, ?int $topicId, ?string $note, int $userId): void
    {
        $this->db->run('UPDATE topic_suggestions SET status = ?, topic_id = ?, decision_note = ?, decided_by = ?, decided_at = NOW() WHERE id = ?',
            [$status, $topicId, $note, $userId, $id]);
    }

    /** @return array<string, int> what is waiting for the admin */
    public function waiting(): array
    {
        return [
            'resources' => (int) $this->db->scalar("SELECT COUNT(*) FROM school_resources WHERE status = 'new'"),
            'suggestions' => (int) $this->db->scalar("SELECT COUNT(*) FROM topic_suggestions WHERE status = 'pending'"),
            'calendar' => (int) $this->db->scalar("SELECT COUNT(*) FROM calendar_confirmations c WHERE status = 'problem' AND resolved_at IS NULL
                AND c.id = (SELECT MAX(id) FROM calendar_confirmations x WHERE x.school_id = c.school_id AND x.year_id = c.year_id)"),
        ];
    }
}
