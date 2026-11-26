<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** MANEB results students share, and the figures behind the revision page. */
final class ManebRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Per form and subject: topics, published lessons, lessons this student has watched, published notes.
     * @param list<int> $forms
     * @return list<array<string, mixed>>
     */
    public function revisionCounts(int $userId, array $forms): array
    {
        if ($forms === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($forms), '?'));

        return $this->db->all(
            "SELECT t.form, t.subject, COUNT(DISTINCT t.id) AS topics,
                    COUNT(DISTINCT l.id) AS lessons,
                    COUNT(DISTINCT v.lesson_id) AS watched,
                    (SELECT COUNT(*) FROM notes n WHERE n.form = t.form AND n.subject = t.subject AND n.status = 'published') AS notes
             FROM topics t
             LEFT JOIN lessons l ON l.topic_id = t.id AND l.status = 'published'
             LEFT JOIN lesson_views v ON v.lesson_id = l.id AND v.user_id = ?
             WHERE t.is_active = 1 AND t.form IN ($in)
             GROUP BY t.form, t.subject ORDER BY t.form, t.subject",
            [$userId, ...$forms]
        );
    }

    /** @return array<string, mixed>|null */
    public function result(int $id): ?array
    {
        return $this->db->one('SELECT r.*, u.full_name FROM maneb_results r JOIN users u ON u.id = r.user_id WHERE r.id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function resultsFor(int $userId): array
    {
        return $this->db->all('SELECT * FROM maneb_results WHERE user_id = ? ORDER BY year DESC, exam DESC', [$userId]);
    }

    /** @param array<string, mixed> $d exam, year, grades (JSON), subjects_sat, subjects_passed, points, reflection, share */
    public function saveResult(int $userId, array $d): int
    {
        $existing = $this->db->one('SELECT id, grades, subjects_sat, subjects_passed, points FROM maneb_results WHERE user_id = ? AND exam = ? AND year = ?', [$userId, $d['exam'], $d['year']]);
        if ($existing !== null) {
            // Changing the grades themselves clears a tutor's verification; sharing choices do not.
            $changed = json_decode((string) $existing['grades'], true) != json_decode((string) $d['grades'], true)
                || (string) $existing['subjects_sat'] !== (string) ($d['subjects_sat'] ?? '')
                || (string) $existing['subjects_passed'] !== (string) ($d['subjects_passed'] ?? '')
                || (string) $existing['points'] !== (string) ($d['points'] ?? '');
            $this->db->run(
                'UPDATE maneb_results SET grades = ?, subjects_sat = ?, subjects_passed = ?, points = ?, reflection = ?, share = ?'
                . ($changed ? ', verified_at = NULL, verified_by = NULL' : '') . ' WHERE id = ?',
                [$d['grades'], $d['subjects_sat'], $d['subjects_passed'], $d['points'], $d['reflection'], $d['share'], $existing['id']]
            );

            return (int) $existing['id'];
        }

        return $this->db->insert(
            'INSERT INTO maneb_results (user_id, exam, year, grades, subjects_sat, subjects_passed, points, reflection, share) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $d['exam'], $d['year'], $d['grades'], $d['subjects_sat'], $d['subjects_passed'], $d['points'], $d['reflection'], $d['share']]
        );
    }

    public function verify(int $id, int $tutorId): void
    {
        $this->db->run('UPDATE maneb_results SET verified_at = NOW(), verified_by = ? WHERE id = ?', [$tutorId, $id]);
    }

    /** @return list<array<string, mixed>> every result (tutor), newest first */
    public function allResults(): array
    {
        return $this->db->all(
            'SELECT r.*, u.full_name, u.role FROM maneb_results r JOIN users u ON u.id = r.user_id ORDER BY r.year DESC, r.exam, u.full_name'
        );
    }

    /** @return list<array<string, mixed>> results students agreed to include in the public summary */
    public function shared(): array
    {
        return $this->db->all(
            "SELECT r.exam, r.year, r.grades, r.subjects_sat, r.subjects_passed, r.points, r.share, r.verified_at, r.reflection,
                    u.full_name, u.gender
             FROM maneb_results r JOIN users u ON u.id = r.user_id
             WHERE r.share IN ('summary', 'named') ORDER BY r.year DESC, r.exam"
        );
    }
}
