<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Grade bands, category weights, every marked piece of work, and issued term reports. */
final class GradeRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, list<array{grade: string, min_percent: int, descriptor: string}>> scale => bands, best first */
    public function bands(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM grade_bands ORDER BY scale, min_percent DESC') as $r) {
            $out[(string) $r['scale']][] = ['grade' => (string) $r['grade'], 'min_percent' => (int) $r['min_percent'], 'descriptor' => (string) $r['descriptor']];
        }

        return $out;
    }

    public function saveBand(string $scale, string $grade, int $minPercent, string $descriptor): void
    {
        $this->db->run('UPDATE grade_bands SET min_percent = ?, descriptor = ? WHERE scale = ? AND grade = ?', [$minPercent, $descriptor, $scale, $grade]);
    }

    public function setting(string $name): ?string
    {
        $v = $this->db->scalar('SELECT value FROM settings WHERE name = ?', [$name]);

        return $v === null ? null : (string) $v;
    }

    public function saveSetting(string $name, string $value): void
    {
        $this->db->run('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
    }

    /**
     * Every marked piece of work in a date range (term), for one student or all.
     * @return list<array<string, mixed>> user_id, subject, kind, score, max_score, submitted_at, title
     */
    public function markedWork(string $from, string $until, ?int $userId = null): array
    {
        return $this->db->all(
            "SELECT s.user_id, a.subject, a.kind, a.title, a.id AS assessment_id, s.score, s.max_score, s.submitted_at, a.results_at
             FROM submissions s JOIN assessments a ON a.id = s.assessment_id
             WHERE s.status = 'marked' AND s.max_score > 0 AND s.submitted_at >= ? AND s.submitted_at < ?"
            . ($userId !== null ? ' AND s.user_id = ?' : '') . ' ORDER BY s.submitted_at',
            $userId !== null ? [$from, $until, $userId] : [$from, $until]
        );
    }

    /** @param array<string, mixed> $data */
    public function saveReport(int $userId, int $termId, array $data, ?string $comment, int $issuedBy): int
    {
        $this->db->run(
            'INSERT INTO term_reports (user_id, term_id, data, comment, issued_by) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), comment = VALUES(comment), issued_by = VALUES(issued_by), issued_at = NOW()',
            [$userId, $termId, json_encode($data, JSON_UNESCAPED_UNICODE), $comment, $issuedBy]
        );

        return (int) $this->db->scalar('SELECT id FROM term_reports WHERE user_id = ? AND term_id = ?', [$userId, $termId]);
    }

    public function setReportDocument(int $id, string $code): void
    {
        $this->db->run('UPDATE term_reports SET document_code = ? WHERE id = ?', [$code, $id]);
    }

    /** @return list<array<string, mixed>> a student's issued reports, newest first */
    public function reportsFor(int $userId): array
    {
        return $this->db->all(
            'SELECT r.*, t.number, y.name AS year_name FROM term_reports r JOIN terms t ON t.id = r.term_id JOIN academic_years y ON y.id = t.year_id
             WHERE r.user_id = ? ORDER BY t.opens_on DESC',
            [$userId]
        );
    }

    /** @return array<int, array<string, mixed>> user id => report, for one term */
    public function reportsForTerm(int $termId): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM term_reports WHERE term_id = ?', [$termId]) as $r) {
            $out[(int) $r['user_id']] = $r;
        }

        return $out;
    }
}
