<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

final class LessonRepository
{
    public const DETAIL_FIELDS = ['title', 'form', 'subject', 'summary', 'why_it_matters', 'where_in_malawi', 'careers', 'try_at_home'];

    public function __construct(private readonly Database $db)
    {
    }

    /** @param array{title: string, duration_ms: int, size_bytes: int, sha256: string, parts: int, has_voice: bool, created_by: int} $data */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO lessons (title, duration_ms, size_bytes, sha256, parts, has_voice, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$data['title'], $data['duration_ms'], $data['size_bytes'], $data['sha256'], $data['parts'], $data['has_voice'] ? 1 : 0, $data['created_by']]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM lessons WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all('SELECT * FROM lessons ORDER BY created_at DESC, id DESC');
    }

    /** @return list<array<string, mixed>> */
    public function published(?int $form = null): array
    {
        if ($form === null) {
            return $this->db->all("SELECT * FROM lessons WHERE status = 'published' ORDER BY published_at DESC");
        }

        return $this->db->all("SELECT * FROM lessons WHERE status = 'published' AND form = ? ORDER BY published_at DESC", [$form]);
    }

    /** @param array<string, string|int|null> $details only keys from DETAIL_FIELDS */
    public function updateDetails(int $id, array $details): void
    {
        $details = array_intersect_key($details, array_flip(self::DETAIL_FIELDS));
        if ($details === []) {
            return;
        }
        $sets = implode(', ', array_map(static fn (string $f): string => "$f = ?", array_keys($details)));
        $this->db->run("UPDATE lessons SET $sets WHERE id = ?", [...array_values($details), $id]);
    }

    public function updateParts(int $id, int $parts): void
    {
        $this->db->run('UPDATE lessons SET parts = ? WHERE id = ?', [$parts, $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->run(
            "UPDATE lessons SET status = ?, published_at = IF(? = 'published', COALESCE(published_at, NOW()), published_at) WHERE id = ?",
            [$status, $status, $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM lessons WHERE id = ?', [$id]);
    }
}
