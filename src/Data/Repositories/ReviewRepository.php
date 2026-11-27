<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Teachers' checks of notes, and their signatures. */
final class ReviewRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed>|null an active partner with school, subjects and signature */
    public function reviewer(int $userId): ?array
    {
        return $this->db->one(
            "SELECT u.id, u.full_name, pp.subjects, pp.signature_path, pp.signature_at, s.name AS school_name
             FROM users u JOIN partner_profiles pp ON pp.user_id = u.id LEFT JOIN schools s ON s.id = pp.school_id
             WHERE u.id = ? AND u.role = 'partner' AND u.status = 'active'",
            [$userId]
        );
    }

    public function setSignature(int $userId, string $path): void
    {
        $this->db->run('UPDATE partner_profiles SET signature_path = ?, signature_at = NOW() WHERE user_id = ?', [$path, $userId]);
    }

    /** @return list<int> active partner teachers of a subject */
    public function reviewersFor(string $subject): array
    {
        return array_map('intval', array_column($this->db->all(
            "SELECT u.id FROM users u JOIN partner_profiles pp ON pp.user_id = u.id
             WHERE u.role = 'partner' AND u.status = 'active' AND FIND_IN_SET(?, pp.subjects) > 0",
            [$subject]
        ), 'id'));
    }

    public function requestReview(int $noteId): void
    {
        $this->db->run('UPDATE notes SET review_requested_at = NOW() WHERE id = ?', [$noteId]);
    }

    /** @return list<array<string, mixed>> notes sent for checking */
    public function requested(): array
    {
        return $this->db->all(
            "SELECT n.id, n.title, n.form, n.subject, n.kind, n.status, n.review_requested_at, n.updated_at, t.title AS topic_title
             FROM notes n JOIN topics t ON t.id = n.topic_id
             WHERE n.review_requested_at IS NOT NULL ORDER BY n.review_requested_at DESC"
        );
    }

    /** @return array<string, mixed>|null */
    public function note(int $id): ?array
    {
        return $this->db->one('SELECT n.*, t.title AS topic_title FROM notes n JOIN topics t ON t.id = n.topic_id WHERE n.id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function blocks(int $noteId): array
    {
        return $this->db->all('SELECT * FROM note_blocks WHERE note_id = ? ORDER BY sort_order, id', [$noteId]);
    }

    /** @return list<array<string, mixed>> questions of a checkpoint, with when each was last changed */
    public function questions(int $assessmentId): array
    {
        return $this->db->all(
            'SELECT q.id, q.type, q.prompt, q.data, q.marks, q.updated_at FROM assessment_questions aq JOIN questions q ON q.id = aq.question_id
             WHERE aq.assessment_id = ? ORDER BY aq.sort_order, q.id',
            [$assessmentId]
        );
    }

    /** @param array<string, mixed> $r */
    public function add(array $r): int
    {
        return $this->db->insert(
            'INSERT INTO note_reviews (note_id, reviewer_id, decision, comment, content_hash, reviewer_name, school_name, signature_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$r['note_id'], $r['reviewer_id'], $r['decision'], $r['comment'], $r['content_hash'], $r['reviewer_name'], $r['school_name'], $r['signature_path']]
        );
    }

    /** @return list<array<string, mixed>> newest first */
    public function forNote(int $noteId): array
    {
        return $this->db->all('SELECT * FROM note_reviews WHERE note_id = ? ORDER BY id DESC', [$noteId]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM note_reviews WHERE id = ?', [$id]);
    }
}
