<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

final class TopicRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Topics (with subtopics) for one form and subject, plus request and lesson counts.
     * @return list<array<string, mixed>>
     */
    public function forFormSubject(int $form, string $subject, bool $includeHidden = false): array
    {
        return $this->db->all(
            'SELECT t.*,
                    r.id AS request_id, r.status AS request_status, r.is_exception, r.exception_status,
                    (CASE WHEN r.id IS NULL THEN 0 ELSE 1 + (SELECT COUNT(*) FROM topic_request_votes v WHERE v.request_id = r.id) END) AS request_count,
                    (SELECT COUNT(*) FROM lessons l WHERE l.topic_id = t.id AND l.status = \'published\') AS lesson_count
             FROM topics t
             LEFT JOIN topic_requests r ON r.topic_id = t.id
             WHERE t.form = ? AND t.subject = ?' . ($includeHidden ? '' : ' AND t.is_active = 1') . '
             ORDER BY COALESCE(t.parent_id, t.id), t.parent_id IS NOT NULL, t.sort_order, t.id',
            [$form, $subject]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM topics WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function children(int $parentId): array
    {
        return $this->db->all('SELECT * FROM topics WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order, id', [$parentId]);
    }

    /** @return list<array{id: int, form: int, subject: string, label: string}> for choosing a lesson's topic */
    public function options(): array
    {
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'form' => (int) $r['form'],
            'subject' => (string) $r['subject'],
            'label' => ($r['parent_title'] !== null ? $r['parent_title'] . ' › ' : '') . $r['title'],
        ], $this->db->all(
            'SELECT t.id, t.form, t.subject, t.title, p.title AS parent_title FROM topics t
             LEFT JOIN topics p ON p.id = t.parent_id
             WHERE t.is_active = 1 ORDER BY t.form, t.subject, COALESCE(t.parent_id, t.id), t.parent_id IS NOT NULL, t.sort_order'
        ));
    }

    public function nextSortOrder(int $form, string $subject, ?int $parentId): int
    {
        $max = $parentId === null
            ? $this->db->scalar('SELECT MAX(sort_order) FROM topics WHERE form = ? AND subject = ? AND parent_id IS NULL', [$form, $subject])
            : $this->db->scalar('SELECT MAX(sort_order) FROM topics WHERE parent_id = ?', [$parentId]);

        return (int) $max + 10;
    }

    public function create(int $form, string $subject, ?int $parentId, ?string $unit, string $title, int $sortOrder, ?string $objectives = null): int
    {
        return $this->db->insert(
            'INSERT INTO topics (form, subject, parent_id, unit, title, objectives, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$form, $subject, $parentId, $unit, $title, $objectives, $sortOrder]
        );
    }

    public function update(int $id, ?string $unit, string $title, ?string $objectives, int $sortOrder, bool $active): void
    {
        $this->db->run(
            'UPDATE topics SET unit = ?, title = ?, objectives = ?, sort_order = ?, is_active = ? WHERE id = ?',
            [$unit, $title, $objectives, $sortOrder, $active ? 1 : 0, $id]
        );
    }

    /** @return list<array<string, mixed>> published lessons linked to a topic or its subtopics */
    public function lessons(int $topicId): array
    {
        return $this->db->all(
            "SELECT l.id, l.title, l.duration_ms FROM lessons l
             JOIN topics t ON t.id = l.topic_id
             WHERE l.status = 'published' AND (t.id = ? OR t.parent_id = ?)
             ORDER BY l.published_at DESC",
            [$topicId, $topicId]
        );
    }

    /** What the student's school reported for this topic or its parent: covered | covering | next | null */
    public function coverage(int $schoolId, int $topicId): ?string
    {
        $status = $this->db->scalar(
            "SELECT c.status FROM topic_coverage c JOIN topics t ON t.id = ?
             WHERE c.school_id = ? AND (c.topic_id = t.id OR c.topic_id = t.parent_id)
             ORDER BY FIELD(c.status, 'covered', 'covering', 'next') LIMIT 1",
            [$topicId, $schoolId]
        );

        return $status === null || $status === false ? null : (string) $status;
    }

    /** @return list<array{id: int|string, form: int|string, subject: string}> */
    public function allActive(): array
    {
        return $this->db->all('SELECT id, form, subject FROM topics WHERE is_active = 1');
    }

    /** @return array<int, string> topic id => subject */
    /** @return array<int, int> topic id => form */
    public function formMap(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT id, form FROM topics') as $r) {
            $out[(int) $r['id']] = (int) $r['form'];
        }

        return $out;
    }

    public function subjectMap(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT id, subject FROM topics') as $r) {
            $out[(int) $r['id']] = (string) $r['subject'];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> active main topics with no published lesson on them or their subtopics */
    public function mainTopicsWithoutLessons(): array
    {
        return $this->db->all(
            "SELECT t.id, t.form, t.subject, t.unit, t.title FROM topics t
             WHERE t.parent_id IS NULL AND t.is_active = 1
               AND NOT EXISTS (SELECT 1 FROM lessons l JOIN topics x ON x.id = l.topic_id
                               WHERE l.status = 'published' AND (x.id = t.id OR x.parent_id = t.id))"
        );
    }
}
