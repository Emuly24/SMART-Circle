<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Weekly real-life challenges and the "Spotted in real life" wall. */
final class ChallengeRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $c */
    public function create(array $c, int $by): int
    {
        return $this->db->insert(
            'INSERT INTO challenges (form, subject, topic_id, title, body, explanation, opens_at, closes_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$c['form'], $c['subject'], $c['topic_id'], $c['title'], $c['body'], $c['explanation'], $c['opens_at'], $c['closes_at'], $by]
        );
    }

    public function setExplanation(int $id, string $text): void
    {
        $this->db->run('UPDATE challenges SET explanation = ? WHERE id = ?', [$text, $id]);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(
            "SELECT c.*, t.title AS topic_title, (SELECT COUNT(*) FROM comments x WHERE x.target_type = 'challenge' AND x.target_id = c.id) AS answers
             FROM challenges c LEFT JOIN topics t ON t.id = c.topic_id ORDER BY c.opens_at DESC LIMIT 100"
        );
    }

    /** @return list<array<string, mixed>> challenges for these forms that have opened */
    public function forForms(array $forms): array
    {
        $forms = array_values(array_map('intval', $forms));
        if ($forms === []) {
            return [];
        }

        return $this->db->all(
            'SELECT c.*, t.title AS topic_title FROM challenges c LEFT JOIN topics t ON t.id = c.topic_id
             WHERE c.form IN (' . implode(',', array_fill(0, count($forms), '?')) . ') AND c.opens_at <= NOW() ORDER BY c.opens_at DESC LIMIT 30',
            $forms
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT c.*, t.title AS topic_title FROM challenges c LEFT JOIN topics t ON t.id = c.topic_id WHERE c.id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->run("DELETE FROM comments WHERE target_type = 'challenge' AND target_id = ?", [$id]);
        $this->db->run('DELETE FROM challenges WHERE id = ?', [$id]);
    }

    /* ---------- The wall ---------- */

    public function addPost(int $userId, ?int $topicId, string $caption, string $photo): int
    {
        return $this->db->insert('INSERT INTO wall_posts (user_id, topic_id, caption, photo_path) VALUES (?, ?, ?, ?)', [$userId, $topicId, $caption, $photo]);
    }

    /** @return list<array<string, mixed>> */
    public function posts(string $status, int $limit = 60): array
    {
        return $this->db->all(
            'SELECT w.*, u.full_name, sp.form, t.title AS topic_title FROM wall_posts w JOIN users u ON u.id = w.user_id
             LEFT JOIN student_profiles sp ON sp.user_id = u.id LEFT JOIN topics t ON t.id = w.topic_id
             WHERE w.status = ? ORDER BY w.featured DESC, w.created_at DESC LIMIT ' . max(1, min(200, $limit)),
            [$status]
        );
    }

    /** @return list<array<string, mixed>> */
    public function myPosts(int $userId): array
    {
        return $this->db->all('SELECT * FROM wall_posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 20', [$userId]);
    }

    /** @return array<string, mixed>|null */
    public function post(int $id): ?array
    {
        return $this->db->one('SELECT * FROM wall_posts WHERE id = ?', [$id]);
    }

    public function setPost(int $id, string $status, bool $featured): void
    {
        $this->db->run('UPDATE wall_posts SET status = ?, featured = ? WHERE id = ?', [$status, $featured ? 1 : 0, $id]);
    }

    public function postsToday(int $userId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM wall_posts WHERE user_id = ? AND created_at >= CURDATE()', [$userId]);
    }
}
