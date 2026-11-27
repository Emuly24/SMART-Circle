<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Teachers' checks of recorded lessons (and their PDF handouts). */
final class LessonReviewRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function lesson(int $id): ?array
    {
        return $this->db->one('SELECT l.*, t.title AS topic_title FROM lessons l LEFT JOIN topics t ON t.id = l.topic_id WHERE l.id = ?', [$id]);
    }

    public function requestReview(int $lessonId): void
    {
        $this->db->run('UPDATE lessons SET review_requested_at = NOW() WHERE id = ?', [$lessonId]);
    }

    /** @return list<array<string, mixed>> */
    public function requested(): array
    {
        return $this->db->all(
            'SELECT l.id, l.title, l.form, l.subject, l.status, l.review_requested_at, t.title AS topic_title
             FROM lessons l LEFT JOIN topics t ON t.id = l.topic_id WHERE l.review_requested_at IS NOT NULL ORDER BY l.review_requested_at DESC'
        );
    }

    /** @param array<string, mixed> $r */
    public function add(array $r): int
    {
        return $this->db->insert(
            'INSERT INTO lesson_reviews (lesson_id, reviewer_id, decision, comment, content_hash, reviewer_name, school_name, signature_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$r['lesson_id'], $r['reviewer_id'], $r['decision'], $r['comment'], $r['content_hash'], $r['reviewer_name'], $r['school_name'], $r['signature_path']]
        );
    }

    /** @return list<array<string, mixed>> newest first */
    public function forLesson(int $lessonId): array
    {
        return $this->db->all('SELECT * FROM lesson_reviews WHERE lesson_id = ? ORDER BY id DESC', [$lessonId]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM lesson_reviews WHERE id = ?', [$id]);
    }
}
