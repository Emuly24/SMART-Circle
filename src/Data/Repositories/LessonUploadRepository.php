<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

final class LessonUploadRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->db->one('SELECT * FROM lesson_uploads WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null an earlier upload of the same file from the same device */
    public function findByClient(int $userId, string $clientKey, string $sha256): ?array
    {
        return $this->db->one(
            'SELECT * FROM lesson_uploads WHERE user_id = ? AND client_key = ? AND sha256 = ?',
            [$userId, $clientKey, $sha256]
        );
    }

    public function create(string $id, int $userId, string $clientKey, string $title, int $size, string $sha256): void
    {
        $this->db->run(
            'INSERT INTO lesson_uploads (id, user_id, client_key, title, size_bytes, sha256) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $userId, $clientKey, $title, $size, $sha256]
        );
    }

    public function setReceived(string $id, int $bytes): void
    {
        $this->db->run('UPDATE lesson_uploads SET received_bytes = ? WHERE id = ?', [$bytes, $id]);
    }

    public function complete(string $id, int $lessonId): void
    {
        $this->db->run("UPDATE lesson_uploads SET status = 'complete', lesson_id = ? WHERE id = ?", [$lessonId, $id]);
    }

    public function delete(string $id): void
    {
        $this->db->run('DELETE FROM lesson_uploads WHERE id = ?', [$id]);
    }

    /** @return list<string> ids of unfinished uploads untouched for $days days */
    public function staleIds(int $days): array
    {
        return array_map('strval', array_column($this->db->all(
            "SELECT id FROM lesson_uploads WHERE status = 'uploading' AND updated_at < NOW() - INTERVAL ? DAY",
            [$days]
        ), 'id'));
    }
}
