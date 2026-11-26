<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** The tutor's question bank. `data` is stored as JSON and decoded on read. */
final class QuestionRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $q */
    public function create(array $q, int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO questions (form, subject, topic_id, type, prompt, data, marks, is_application, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$q['form'], $q['subject'], $q['topic_id'], $q['type'], $q['prompt'], json_encode($q['data'], JSON_UNESCAPED_UNICODE), $q['marks'], $q['is_application'] ? 1 : 0, $createdBy]
        );
    }

    /** @param array<string, mixed> $q */
    public function update(int $id, array $q): void
    {
        $this->db->run(
            'UPDATE questions SET form = ?, subject = ?, topic_id = ?, type = ?, prompt = ?, data = ?, marks = ?, is_application = ? WHERE id = ?',
            [$q['form'], $q['subject'], $q['topic_id'], $q['type'], $q['prompt'], json_encode($q['data'], JSON_UNESCAPED_UNICODE), $q['marks'], $q['is_application'] ? 1 : 0, $id]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $q = $this->db->one(
            'SELECT q.*, t.title AS topic_title FROM questions q LEFT JOIN topics t ON t.id = q.topic_id WHERE q.id = ?',
            [$id]
        );

        return $q === null ? null : self::decode($q);
    }

    /** @return list<array<string, mixed>> */
    public function forFormSubject(int $form, string $subject): array
    {
        return array_map([self::class, 'decode'], $this->db->all(
            'SELECT q.*, t.title AS topic_title,
                    (SELECT COUNT(*) FROM assessment_questions aq WHERE aq.question_id = q.id) AS used
             FROM questions q LEFT JOIN topics t ON t.id = q.topic_id
             WHERE q.form = ? AND q.subject = ? ORDER BY t.sort_order IS NULL, t.sort_order, q.id',
            [$form, $subject]
        ));
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM questions WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $q @return array<string, mixed> */
    public static function decode(array $q): array
    {
        $q['data'] = json_decode((string) ($q['data'] ?? ''), true) ?: [];

        return $q;
    }
}
