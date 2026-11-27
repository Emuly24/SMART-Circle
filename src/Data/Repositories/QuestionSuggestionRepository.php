<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Questions suggested by partner teachers, waiting for the tutor. */
final class QuestionSuggestionRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $q clean question (QuestionForm) */
    public function add(int $partnerId, array $q): int
    {
        return $this->db->insert(
            'INSERT INTO question_suggestions (partner_id, form, subject, topic_id, type, prompt, data, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$partnerId, $q['form'], $q['subject'], $q['topic_id'], $q['type'], $q['prompt'], json_encode($q['data'], JSON_UNESCAPED_UNICODE), $q['marks']]
        );
    }

    /** @return list<array<string, mixed>> */
    public function forPartner(int $partnerId): array
    {
        return $this->db->all(
            'SELECT s.*, t.title AS topic_title FROM question_suggestions s LEFT JOIN topics t ON t.id = s.topic_id WHERE s.partner_id = ? ORDER BY s.id DESC',
            [$partnerId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function byStatus(string $status): array
    {
        return $this->db->all(
            "SELECT s.*, t.title AS topic_title, u.full_name AS partner_name, sc.name AS school_name
             FROM question_suggestions s LEFT JOIN topics t ON t.id = s.topic_id LEFT JOIN users u ON u.id = s.partner_id
             LEFT JOIN partner_profiles pp ON pp.user_id = s.partner_id LEFT JOIN schools sc ON sc.id = pp.school_id
             WHERE s.status = ? ORDER BY s.created_at" . ($status === 'pending' ? '' : ' DESC LIMIT 50'),
            [$status]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM question_suggestions WHERE id = ?', [$id]);
    }

    public function decide(int $id, string $status, ?string $note, ?int $questionId): void
    {
        $this->db->run("UPDATE question_suggestions SET status = ?, tutor_note = ?, question_id = ?, decided_at = NOW() WHERE id = ? AND status = 'pending'",
            [$status, $note, $questionId, $id]);
    }

    public function countPending(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM question_suggestions WHERE status = 'pending'");
    }
}
