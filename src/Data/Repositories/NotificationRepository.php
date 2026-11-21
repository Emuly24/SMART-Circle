<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

final class NotificationRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param list<int> $userIds */
    public function createMany(array $userIds, string $type, string $title, ?string $body, ?string $link): void
    {
        foreach (array_unique($userIds) as $userId) {
            $this->db->run(
                'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)',
                [$userId, $type, $title, $body, $link]
            );
        }
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $userId, int $limit = 50): array
    {
        return $this->db->all(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(200, $limit)),
            [$userId]
        );
    }

    public function markAllRead(int $userId): void
    {
        $this->db->run('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }
}
