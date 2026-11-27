<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Badges (achievements) and exit tickets. */
final class ProgressRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, string> code => earned_at */
    public function badges(int $userId): array
    {
        $out = [];
        foreach ($this->db->all('SELECT code, earned_at FROM achievements WHERE user_id = ? ORDER BY earned_at', [$userId]) as $r) {
            $out[(string) $r['code']] = (string) $r['earned_at'];
        }

        return $out;
    }

    public function award(int $userId, string $code): bool
    {
        return $this->db->run('INSERT IGNORE INTO achievements (user_id, code) VALUES (?, ?)', [$userId, $code])->rowCount() === 1;
    }

    /** @return array<string, int> the figures badges are earned from */
    public function figures(int $userId): array
    {
        $q = fn (string $sql): int => (int) $this->db->scalar($sql, [$userId]);

        return [
            'lessons' => $q('SELECT COUNT(DISTINCT lesson_id) FROM lesson_views WHERE user_id = ?'),
            'study_days_7' => $q('SELECT COUNT(DISTINCT DATE(last_watched_at)) FROM lesson_views WHERE user_id = ? AND last_watched_at > NOW() - INTERVAL 7 DAY'),
            'work' => $q("SELECT COUNT(*) FROM submissions WHERE user_id = ? AND status <> 'in_progress'"),
            'exit_tickets' => $q('SELECT COUNT(*) FROM exit_tickets WHERE user_id = ?'),
            'questions_asked' => $q("SELECT COUNT(*) FROM comments WHERE user_id = ? AND target_type = 'lesson' AND parent_id IS NULL AND status = 'visible'"),
            'helped' => $q("SELECT COUNT(*) FROM comments c WHERE c.user_id = ? AND c.parent_id IS NOT NULL AND c.status = 'visible'
                            AND (c.is_approved_answer = 1 OR EXISTS (SELECT 1 FROM reactions r WHERE r.comment_id = c.id AND r.kind = 'thanks' AND r.user_id <> c.user_id))"),
            'challenges' => $q("SELECT COUNT(DISTINCT target_id) FROM comments WHERE user_id = ? AND target_type = 'challenge'"),
            'wall_featured' => $q('SELECT COUNT(*) FROM wall_posts WHERE user_id = ? AND featured = 1 AND status = \'visible\''),
            'wall_posts' => $q("SELECT COUNT(*) FROM wall_posts WHERE user_id = ? AND status = 'visible'"),
        ];
    }

    /* ---------- Exit tickets ---------- */

    public function saveTicket(int $lessonId, int $userId, int $understanding, ?string $use, ?string $unclear): void
    {
        $this->db->run(
            'INSERT INTO exit_tickets (lesson_id, user_id, understanding, real_life_use, still_unclear) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE understanding = VALUES(understanding), real_life_use = VALUES(real_life_use), still_unclear = VALUES(still_unclear), created_at = NOW()',
            [$lessonId, $userId, $understanding, $use, $unclear]
        );
    }

    /** @return array<string, mixed>|null */
    public function ticket(int $lessonId, int $userId): ?array
    {
        return $this->db->one('SELECT * FROM exit_tickets WHERE lesson_id = ? AND user_id = ?', [$lessonId, $userId]);
    }

    /** @return list<array<string, mixed>> */
    public function tickets(int $lessonId): array
    {
        return $this->db->all(
            'SELECT e.*, u.full_name FROM exit_tickets e JOIN users u ON u.id = e.user_id WHERE e.lesson_id = ? ORDER BY e.created_at DESC',
            [$lessonId]
        );
    }
}
