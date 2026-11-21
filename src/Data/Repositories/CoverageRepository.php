<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** What each school reports it has taught (topic_coverage). */
final class CoverageRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, string> topic id => covered | covering | next, for one school/form/subject */
    public function forSchool(int $schoolId, int $form, string $subject): array
    {
        $out = [];
        foreach ($this->db->all(
            'SELECT c.topic_id, c.status FROM topic_coverage c JOIN topics t ON t.id = c.topic_id
             WHERE c.school_id = ? AND t.form = ? AND t.subject = ?',
            [$schoolId, $form, $subject]
        ) as $r) {
            $out[(int) $r['topic_id']] = (string) $r['status'];
        }

        return $out;
    }

    /**
     * Replace one school's reports for the given topics.
     * @param array<int, string> $statuses topic id => status ('' = not yet, removes the report)
     */
    public function save(int $schoolId, array $statuses, int $reportedBy, string $source = 'partner'): void
    {
        $this->db->transaction(function (Database $db) use ($schoolId, $statuses, $reportedBy, $source): void {
            foreach ($statuses as $topicId => $status) {
                if ($status === '') {
                    $db->run('DELETE FROM topic_coverage WHERE school_id = ? AND topic_id = ?', [$schoolId, $topicId]);
                    continue;
                }
                $db->run(
                    'INSERT INTO topic_coverage (school_id, topic_id, status, source, reported_by) VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE reported_by = IF(status = VALUES(status), reported_by, VALUES(reported_by)),
                        source = IF(status = VALUES(status), source, VALUES(source)),
                        reported_at = IF(status = VALUES(status), reported_at, NOW()), status = VALUES(status)',
                    [$schoolId, $topicId, $status, $source, $reportedBy]
                );
            }
        });
    }

    /**
     * Progress per form and subject for one school.
     * @return array<string, array{covered: int, total: int}> "form subject" => counts (main topics only)
     */
    public function progress(int $schoolId): array
    {
        $out = [];
        foreach ($this->db->all(
            "SELECT t.form, t.subject, COUNT(*) AS total,
                    SUM(c.status = 'covered') AS covered
             FROM topics t
             LEFT JOIN topic_coverage c ON c.topic_id = t.id AND c.school_id = ?
             WHERE t.parent_id IS NULL AND t.is_active = 1
             GROUP BY t.form, t.subject",
            [$schoolId]
        ) as $r) {
            $out[$r['form'] . ' ' . $r['subject']] = ['covered' => (int) $r['covered'], 'total' => (int) $r['total']];
        }

        return $out;
    }

    /** @return list<array{school: string, district: string, status: string}> who reported a topic (or its parent) */
    public function forTopic(int $topicId): array
    {
        return array_map(static fn (array $r): array => [
            'school' => (string) $r['name'],
            'district' => (string) $r['district'],
            'status' => (string) $r['status'],
        ], $this->db->all(
            "SELECT s.name, s.district, MIN(c.status) AS status FROM topic_coverage c
             JOIN schools s ON s.id = c.school_id
             JOIN topics t ON t.id = ?
             WHERE c.topic_id = t.id OR c.topic_id = t.parent_id
             GROUP BY s.id, s.name, s.district ORDER BY s.name",
            [$topicId]
        ));
    }

    public function markChecked(int $schoolId): void
    {
        $this->db->run('UPDATE schools SET coverage_checked_at = NOW() WHERE id = ?', [$schoolId]);
    }

    /** Latest report or "nothing changed" for a school (NULL = never). */
    public function lastUpdate(int $schoolId): ?string
    {
        $v = $this->db->scalar(
            'SELECT GREATEST(COALESCE((SELECT MAX(reported_at) FROM topic_coverage WHERE school_id = ?), \'1000-01-01\'),
                             COALESCE(coverage_checked_at, \'1000-01-01\')) FROM schools WHERE id = ?',
            [$schoolId, $schoolId]
        );

        return $v === null || str_starts_with((string) $v, '1000-') ? null : (string) $v;
    }
}
