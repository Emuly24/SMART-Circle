<?php

declare(strict_types=1);

namespace SmartCircle\Data;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Stores PHP sessions in the `sessions` table, so every page and every request
 * sees the same session (the old site's file/DB "split-brain" cannot happen).
 */
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $data = $this->db->scalar('SELECT data FROM sessions WHERE id = ?', [$id]);

        return is_string($data) ? $data : '';
    }

    public function write(string $id, string $data): bool
    {
        $userId = null;
        // Keep user_id as a column so an admin can see/kill a user's sessions.
        if (preg_match('/auth_user_id\|i:(\d+);/', $data, $m)) {
            $userId = (int) $m[1];
        }

        $this->db->run(
            'INSERT INTO sessions (id, user_id, data, last_activity) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), data = VALUES(data), last_activity = VALUES(last_activity)',
            [$id, $userId, $data, time()]
        );

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->db->run('DELETE FROM sessions WHERE id = ?', [$id]);

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->db->run('DELETE FROM sessions WHERE last_activity < ?', [time() - $max_lifetime])->rowCount();
    }

    /** With session.use_strict_mode, unknown IDs sent by a browser are rejected. */
    public function validateId(string $id): bool
    {
        return $this->db->scalar('SELECT 1 FROM sessions WHERE id = ?', [$id]) !== null;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->db->run('UPDATE sessions SET last_activity = ? WHERE id = ?', [time(), $id]);

        return true;
    }
}
