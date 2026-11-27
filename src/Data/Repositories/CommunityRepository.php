<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** The alumni community board. Posts and replies can be hidden by the tutor, never deleted. */
final class CommunityRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function posts(bool $includeHidden, bool $studentsOnly): array
    {
        $where = [];
        if (!$includeHidden) {
            $where[] = "p.status = 'visible'";
        }
        if ($studentsOnly) {
            $where[] = 'p.for_students = 1';
        }

        return $this->db->all(
            'SELECT p.*, u.full_name, u.role, a.left_year,
                    (SELECT COUNT(*) FROM community_replies r WHERE r.post_id = p.id' . ($includeHidden ? '' : " AND r.status = 'visible'") . ') AS replies
             FROM community_posts p LEFT JOIN users u ON u.id = p.user_id LEFT JOIN alumni_profiles a ON a.user_id = p.user_id'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.last_reply_at DESC, p.id DESC LIMIT 200'
        );
    }

    /** @return array<string, mixed>|null */
    public function post(int $id): ?array
    {
        return $this->db->one(
            'SELECT p.*, u.full_name, u.role, a.left_year FROM community_posts p LEFT JOIN users u ON u.id = p.user_id
             LEFT JOIN alumni_profiles a ON a.user_id = p.user_id WHERE p.id = ?',
            [$id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function replies(int $postId, bool $includeHidden): array
    {
        return $this->db->all(
            'SELECT r.*, u.full_name, u.role, a.left_year FROM community_replies r LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN alumni_profiles a ON a.user_id = r.user_id WHERE r.post_id = ?' . ($includeHidden ? '' : " AND r.status = 'visible'")
            . ' ORDER BY r.created_at, r.id',
            [$postId]
        );
    }

    public function createPost(int $userId, string $kind, string $title, string $body): int
    {
        return $this->db->insert('INSERT INTO community_posts (user_id, kind, title, body) VALUES (?, ?, ?, ?)', [$userId, $kind, $title, $body]);
    }

    public function reply(int $postId, int $userId, string $body): int
    {
        $id = $this->db->insert('INSERT INTO community_replies (post_id, user_id, body) VALUES (?, ?, ?)', [$postId, $userId, $body]);
        $this->db->run('UPDATE community_posts SET last_reply_at = NOW() WHERE id = ?', [$postId]);

        return $id;
    }

    public function setPostStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE community_posts SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function setReplyStatus(int $id, string $status): ?int
    {
        $postId = $this->db->scalar('SELECT post_id FROM community_replies WHERE id = ?', [$id]);
        $this->db->run('UPDATE community_replies SET status = ? WHERE id = ?', [$status, $id]);

        return $postId === null ? null : (int) $postId;
    }

    public function setForStudents(int $id, bool $on): void
    {
        $this->db->run('UPDATE community_posts SET for_students = ? WHERE id = ?', [$on ? 1 : 0, $id]);
    }

    public function postsToday(int $userId): int
    {
        return (int) $this->db->scalar(
            'SELECT (SELECT COUNT(*) FROM community_posts WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 DAY)
                  + (SELECT COUNT(*) FROM community_replies WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 DAY)',
            [$userId, $userId]
        );
    }

    /** @return list<int> alumni and tutors, to tell about new posts */
    public function memberIds(): array
    {
        return array_map('intval', array_column($this->db->all("SELECT id FROM users WHERE role IN ('alumni', 'tutor') AND status = 'active'"), 'id'));
    }
}
