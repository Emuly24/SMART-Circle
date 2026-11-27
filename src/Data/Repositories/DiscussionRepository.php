<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Comments with replies, mentions, reactions, thread follows, hashtags and reports. */
final class DiscussionRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> comments on a target, oldest first, with author details and reaction counts */
    public function thread(string $type, int $id, bool $includeHidden): array
    {
        return $this->db->all(
            "SELECT c.*, u.full_name, u.role, u.avatar_at, sp.form AS author_form,
                    (SELECT COUNT(*) FROM reactions r WHERE r.comment_id = c.id AND r.kind = 'same_question') AS same_count,
                    (SELECT COUNT(*) FROM reactions r WHERE r.comment_id = c.id AND r.kind = 'thanks') AS thanks_count
             FROM comments c JOIN users u ON u.id = c.user_id LEFT JOIN student_profiles sp ON sp.user_id = u.id
             WHERE c.target_type = ? AND c.target_id = ?" . ($includeHidden ? '' : " AND c.status = 'visible'") . ' ORDER BY c.created_at, c.id',
            [$type, $id]
        );
    }

    /** @return array<string, mixed>|null */
    public function comment(int $id): ?array
    {
        return $this->db->one('SELECT c.*, u.full_name FROM comments c JOIN users u ON u.id = c.user_id WHERE c.id = ?', [$id]);
    }

    public function add(string $type, int $targetId, ?int $parentId, int $userId, string $body, ?int $timeMs): int
    {
        return $this->db->insert(
            'INSERT INTO comments (target_type, target_id, parent_id, user_id, body, lesson_time_ms) VALUES (?, ?, ?, ?, ?, ?)',
            [$type, $targetId, $parentId, $userId, $body, $timeMs]
        );
    }

    public function countToday(int $userId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM comments WHERE user_id = ? AND created_at >= CURDATE()', [$userId]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE comments SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function setApproved(int $id, bool $on): void
    {
        $this->db->run('UPDATE comments SET is_approved_answer = ? WHERE id = ?', [$on ? 1 : 0, $id]);
    }

    /** @return list<array{id: int, full_name: string}> people taking part in a thread (for @mentions) */
    public function participants(string $type, int $id): array
    {
        return $this->db->all(
            'SELECT DISTINCT u.id, u.full_name FROM comments c JOIN users u ON u.id = c.user_id WHERE c.target_type = ? AND c.target_id = ?',
            [$type, $id]
        );
    }

    public function addMention(int $commentId, int $userId): void
    {
        $this->db->run('INSERT IGNORE INTO mentions (comment_id, user_id) VALUES (?, ?)', [$commentId, $userId]);
    }

    /** Toggles a reaction. @return bool true when it is now on */
    public function toggleReaction(int $commentId, int $userId, string $kind): bool
    {
        if ($this->db->run('DELETE FROM reactions WHERE comment_id = ? AND user_id = ? AND kind = ?', [$commentId, $userId, $kind])->rowCount() > 0) {
            return false;
        }
        $this->db->run('INSERT INTO reactions (comment_id, user_id, kind) VALUES (?, ?, ?)', [$commentId, $userId, $kind]);

        return true;
    }

    /** @return list<string> "commentId:kind" the user has reacted with on a target */
    public function myReactions(string $type, int $id, int $userId): array
    {
        return array_map(static fn (array $r): string => $r['comment_id'] . ':' . $r['kind'], $this->db->all(
            'SELECT r.comment_id, r.kind FROM reactions r JOIN comments c ON c.id = r.comment_id WHERE c.target_type = ? AND c.target_id = ? AND r.user_id = ?',
            [$type, $id, $userId]
        ));
    }

    public function follow(int $userId, string $type, string $id, bool $on): void
    {
        $on ? $this->db->run('INSERT IGNORE INTO follows (user_id, target_type, target_id) VALUES (?, ?, ?)', [$userId, $type, $id])
            : $this->db->run('DELETE FROM follows WHERE user_id = ? AND target_type = ? AND target_id = ?', [$userId, $type, $id]);
    }

    public function follows(int $userId, string $type, string $id): bool
    {
        return $this->db->one('SELECT 1 AS x FROM follows WHERE user_id = ? AND target_type = ? AND target_id = ?', [$userId, $type, $id]) !== null;
    }

    /** @return list<int> */
    public function followers(string $type, string $id): array
    {
        return array_map('intval', array_column($this->db->all('SELECT user_id FROM follows WHERE target_type = ? AND target_id = ?', [$type, $id]), 'user_id'));
    }

    /** @param list<string> $tags */
    public function tag(string $targetType, int $targetId, array $tags): void
    {
        foreach ($tags as $t) {
            $this->db->run('INSERT IGNORE INTO tags (name) VALUES (?)', [$t]);
            $tagId = (int) $this->db->scalar('SELECT id FROM tags WHERE name = ?', [$t]);
            $this->db->run('INSERT IGNORE INTO taggings (tag_id, target_type, target_id) VALUES (?, ?, ?)', [$tagId, $targetType, $targetId]);
        }
    }

    /** @return list<array<string, mixed>> visible lesson comments carrying a tag, newest first */
    public function tagged(string $tag): array
    {
        return $this->db->all(
            "SELECT c.id, c.body, c.target_type, c.target_id, c.created_at, u.full_name, u.role, l.title AS lesson_title, l.topic_id, l.form, l.subject
             FROM tags t JOIN taggings g ON g.tag_id = t.id AND g.target_type = 'comment'
             JOIN comments c ON c.id = g.target_id AND c.status = 'visible' AND c.target_type = 'lesson'
             JOIN lessons l ON l.id = c.target_id AND l.status = 'published'
             JOIN users u ON u.id = c.user_id
             WHERE t.name = ? ORDER BY c.created_at DESC LIMIT 100",
            [$tag]
        );
    }

    public function report(string $type, int $id, int $userId, string $reason): void
    {
        $this->db->run("INSERT INTO content_reports (target_type, target_id, user_id, reason) VALUES (?, ?, ?, ?)", [$type, $id, $userId, $reason]);
    }

    /** @return list<array<string, mixed>> open reports with what was reported */
    public function openReports(): array
    {
        return $this->db->all(
            "SELECT r.*, u.full_name AS reporter, c.body AS comment_body, c.status AS comment_status, c.target_type AS on_type, c.target_id AS on_id, cu.full_name AS author,
                    w.caption AS wall_caption, w.status AS wall_status, wu.full_name AS wall_author
             FROM content_reports r JOIN users u ON u.id = r.user_id
             LEFT JOIN comments c ON r.target_type = 'comment' AND c.id = r.target_id LEFT JOIN users cu ON cu.id = c.user_id
             LEFT JOIN wall_posts w ON r.target_type = 'wall_post' AND w.id = r.target_id LEFT JOIN users wu ON wu.id = w.user_id
             WHERE r.status = 'open' ORDER BY r.created_at"
        );
    }

    /** @return array<string, mixed>|null */
    public function findReport(int $id): ?array
    {
        return $this->db->one('SELECT * FROM content_reports WHERE id = ?', [$id]);
    }

    public function resolveReports(string $type, int $id, int $by): void
    {
        $this->db->run("UPDATE content_reports SET status = 'resolved', resolved_by = ? WHERE target_type = ? AND target_id = ? AND status = 'open'", [$by, $type, $id]);
    }

    public function countOpenReports(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM content_reports WHERE status = 'open'");
    }
}
