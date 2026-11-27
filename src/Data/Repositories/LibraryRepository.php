<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** The public library: notes and lesson handouts the tutor has chosen to share with everyone. */
final class LibraryRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> published notes shown in the library */
    public function notes(?int $form, ?string $subject): array
    {
        [$where, $params] = $this->filter('n', $form, $subject);

        return $this->db->all(
            "SELECT n.id, n.kind, n.title, n.form, n.subject, n.summary, n.updated_at, t.title AS topic_title
             FROM notes n JOIN topics t ON t.id = n.topic_id
             WHERE n.status = 'published' AND n.in_library = 1$where ORDER BY n.form, n.subject, t.sort_order, n.title",
            $params
        );
    }

    /** @return list<array<string, mixed>> published lessons with a public PDF handout */
    public function handouts(?int $form, ?string $subject): array
    {
        [$where, $params] = $this->filter('l', $form, $subject);

        return $this->db->all(
            "SELECT l.id, l.title, l.form, l.subject, l.summary, l.handout_bytes, t.title AS topic_title
             FROM lessons l LEFT JOIN topics t ON t.id = l.topic_id
             WHERE l.status = 'published' AND l.handout_public = 1 AND l.handout_path IS NOT NULL$where ORDER BY l.form, l.subject, l.title",
            $params
        );
    }

    /** @return array{0: string, 1: list<int|string>} */
    private function filter(string $alias, ?int $form, ?string $subject): array
    {
        $where = '';
        $params = [];
        if ($form !== null) {
            $where .= " AND $alias.form = ?";
            $params[] = $form;
        }
        if ($subject !== null) {
            $where .= " AND $alias.subject = ?";
            $params[] = $subject;
        }

        return [$where, $params];
    }

    /** @return array<string, mixed>|null a library note (published and shared) */
    public function note(int $id): ?array
    {
        return $this->db->one(
            "SELECT n.*, t.title AS topic_title FROM notes n JOIN topics t ON t.id = n.topic_id
             WHERE n.id = ? AND n.status = 'published' AND n.in_library = 1",
            [$id]
        );
    }

    /** @return list<array<string, mixed>> text and picture blocks only: exercises are for admitted students */
    public function blocks(int $noteId): array
    {
        return $this->db->all("SELECT * FROM note_blocks WHERE note_id = ? AND type IN ('text', 'image') ORDER BY sort_order, id", [$noteId]);
    }

    /** @return array<string, mixed>|null */
    public function handout(int $lessonId): ?array
    {
        return $this->db->one(
            "SELECT id, title, handout_path, handout_bytes FROM lessons WHERE id = ? AND status = 'published' AND handout_public = 1 AND handout_path IS NOT NULL",
            [$lessonId]
        );
    }

    public function setNoteInLibrary(int $noteId, bool $on): void
    {
        $this->db->run('UPDATE notes SET in_library = ? WHERE id = ?', [$on ? 1 : 0, $noteId]);
    }

    /** @return array<string, mixed>|null */
    public function lessonHandout(int $lessonId): ?array
    {
        return $this->db->one('SELECT id, handout_path, handout_bytes, handout_public FROM lessons WHERE id = ?', [$lessonId]);
    }

    public function setHandout(int $lessonId, ?string $path, ?int $bytes): void
    {
        $this->db->run('UPDATE lessons SET handout_path = ?, handout_bytes = ?, handout_public = IF(? IS NULL, 0, handout_public) WHERE id = ?', [$path, $bytes, $path, $lessonId]);
    }

    public function setHandoutPublic(int $lessonId, bool $on): void
    {
        $this->db->run('UPDATE lessons SET handout_public = ? WHERE id = ? AND handout_path IS NOT NULL', [$on ? 1 : 0, $lessonId]);
    }
}
