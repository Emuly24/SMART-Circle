<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Notes and slides, and their blocks (text, image, checkpoint). */
final class NoteRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $n */
    public function create(array $n, int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO notes (kind, title, form, subject, topic_id, summary, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$n['kind'], $n['title'], $n['form'], $n['subject'], $n['topic_id'], $n['summary'], $createdBy]
        );
    }

    /** @param array<string, mixed> $n */
    public function update(int $id, array $n): void
    {
        $this->db->run(
            'UPDATE notes SET kind = ?, title = ?, form = ?, subject = ?, topic_id = ?, summary = ? WHERE id = ?',
            [$n['kind'], $n['title'], $n['form'], $n['subject'], $n['topic_id'], $n['summary'], $id]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one(
            'SELECT n.*, t.title AS topic_title FROM notes n JOIN topics t ON t.id = n.topic_id WHERE n.id = ?',
            [$id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(
            "SELECT n.*, t.title AS topic_title,
                    (SELECT COUNT(*) FROM note_blocks b WHERE b.note_id = n.id) AS blocks,
                    (SELECT COUNT(*) FROM note_blocks b WHERE b.note_id = n.id AND b.type = 'checkpoint') AS checkpoints
             FROM notes n JOIN topics t ON t.id = n.topic_id ORDER BY n.updated_at DESC, n.id DESC"
        );
    }

    /** @return list<array<string, mixed>> */
    public function publishedForForm(int $form): array
    {
        return $this->db->all(
            "SELECT n.*, t.title AS topic_title,
                    (SELECT COUNT(*) FROM note_blocks b WHERE b.note_id = n.id AND b.type = 'checkpoint') AS checkpoints
             FROM notes n JOIN topics t ON t.id = n.topic_id
             WHERE n.status = 'published' AND n.form = ? ORDER BY n.subject, t.sort_order, n.title",
            [$form]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE notes SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM notes WHERE id = ?', [$id]);
    }

    /* ---------- Blocks ---------- */

    /** @return list<array<string, mixed>> in reading order */
    public function blocks(int $noteId): array
    {
        return $this->db->all('SELECT * FROM note_blocks WHERE note_id = ? ORDER BY sort_order, id', [$noteId]);
    }

    /** @return array<string, mixed>|null */
    public function block(int $id): ?array
    {
        return $this->db->one('SELECT * FROM note_blocks WHERE id = ?', [$id]);
    }

    public function addBlock(int $noteId, string $type, ?string $body, ?string $imagePath = null, ?int $assessmentId = null, bool $isFinal = false, string $align = 'center'): int
    {
        $next = (int) $this->db->scalar('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM note_blocks WHERE note_id = ?', [$noteId]);

        return $this->db->insert(
            'INSERT INTO note_blocks (note_id, sort_order, type, body, image_path, align, assessment_id, is_final) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$noteId, $next, $type, $body, $imagePath, $align, $assessmentId, $isFinal ? 1 : 0]
        );
    }

    public function setAlign(int $blockId, string $align): void
    {
        $this->db->run('UPDATE note_blocks SET align = ? WHERE id = ?', [$align, $blockId]);
    }

    public function updateBody(int $id, ?string $body): void
    {
        $this->db->run('UPDATE note_blocks SET body = ? WHERE id = ?', [$body, $id]);
    }

    public function deleteBlock(int $id): void
    {
        $this->db->run('DELETE FROM note_blocks WHERE id = ?', [$id]);
    }

    public function moveBlock(int $noteId, int $blockId, int $direction): void
    {
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $this->blocks($noteId));
        $i = array_search($blockId, $ids, true);
        $j = $i === false ? false : $i + $direction;
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $this->db->transaction(function (Database $db) use ($ids): void {
            foreach ($ids as $k => $id) {
                $db->run('UPDATE note_blocks SET sort_order = ? WHERE id = ?', [($k + 1) * 10, $id]);
            }
        });
    }
}
