<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Textbooks, which schools use them, and the chapter/pages for each topic. */
final class TextbookRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findOrCreate(string $subject, int $form, string $title, string $edition, string $publisher): int
    {
        $id = $this->db->scalar(
            'SELECT id FROM textbooks WHERE subject = ? AND form = ? AND title = ? AND edition = ?',
            [$subject, $form, $title, $edition]
        );
        if ($id !== null) {
            if ($publisher !== '') {
                $this->db->run("UPDATE textbooks SET publisher = ? WHERE id = ? AND publisher = ''", [$publisher, $id]);
            }
            return (int) $id;
        }

        return $this->db->insert(
            'INSERT INTO textbooks (subject, form, title, edition, publisher) VALUES (?, ?, ?, ?, ?)',
            [$subject, $form, $title, $edition, $publisher]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM textbooks WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> the school's textbooks with how many topics have page refs */
    public function forSchool(int $schoolId): array
    {
        return $this->db->all(
            'SELECT b.*, (SELECT COUNT(*) FROM textbook_topic_refs r WHERE r.textbook_id = b.id) AS refs,
                    (SELECT COUNT(*) FROM school_textbooks x WHERE x.textbook_id = b.id) AS schools
             FROM textbooks b JOIN school_textbooks st ON st.textbook_id = b.id
             WHERE st.school_id = ? ORDER BY b.form, b.subject, b.title',
            [$schoolId]
        );
    }

    /** @return list<array<string, mixed>> every known book for a form and subject (to pick instead of retyping) */
    public function known(): array
    {
        return $this->db->all('SELECT * FROM textbooks ORDER BY form, subject, title');
    }

    public function schoolUses(int $schoolId, int $textbookId): bool
    {
        return $this->db->scalar('SELECT 1 FROM school_textbooks WHERE school_id = ? AND textbook_id = ?', [$schoolId, $textbookId]) !== null;
    }

    public function link(int $schoolId, int $textbookId): void
    {
        $this->db->run('INSERT IGNORE INTO school_textbooks (school_id, textbook_id) VALUES (?, ?)', [$schoolId, $textbookId]);
    }

    public function unlink(int $schoolId, int $textbookId): void
    {
        $this->db->run('DELETE FROM school_textbooks WHERE school_id = ? AND textbook_id = ?', [$schoolId, $textbookId]);
    }

    /** @return array<int, array{chapter: string, pages: string}> topic id => ref */
    public function refs(int $textbookId): array
    {
        $out = [];
        foreach ($this->db->all('SELECT topic_id, chapter, pages FROM textbook_topic_refs WHERE textbook_id = ?', [$textbookId]) as $r) {
            $out[(int) $r['topic_id']] = ['chapter' => (string) $r['chapter'], 'pages' => (string) $r['pages']];
        }

        return $out;
    }

    /** @param array<int, array{chapter: string, pages: string}> $refs topic id => ref (both empty = remove) */
    public function saveRefs(int $textbookId, array $refs): void
    {
        $this->db->transaction(function (Database $db) use ($textbookId, $refs): void {
            foreach ($refs as $topicId => $ref) {
                if ($ref['chapter'] === '' && $ref['pages'] === '') {
                    $db->run('DELETE FROM textbook_topic_refs WHERE textbook_id = ? AND topic_id = ?', [$textbookId, $topicId]);
                    continue;
                }
                $db->run(
                    'INSERT INTO textbook_topic_refs (textbook_id, topic_id, chapter, pages) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE chapter = VALUES(chapter), pages = VALUES(pages)',
                    [$textbookId, $topicId, $ref['chapter'], $ref['pages']]
                );
            }
        });
    }

    /**
     * Page references for a topic, its parent and its subtopics, books used at the given school first.
     * @return list<array<string, mixed>>
     */
    public function forTopic(int $topicId, ?int $schoolId): array
    {
        return $this->db->all(
            'SELECT b.id, b.title, b.edition, b.publisher, r.chapter, r.pages, t.title AS topic_title, t.id = ? AS exact,
                    (SELECT 1 FROM school_textbooks st WHERE st.textbook_id = b.id AND st.school_id = ?) AS at_my_school
             FROM textbook_topic_refs r
             JOIN textbooks b ON b.id = r.textbook_id
             JOIN topics t ON t.id = r.topic_id
             JOIN topics me ON me.id = ?
             WHERE r.topic_id = me.id OR r.topic_id = me.parent_id OR t.parent_id = me.id
             ORDER BY at_my_school DESC, exact DESC, b.title, t.sort_order',
            [$topicId, $schoolId ?? 0, $topicId]
        );
    }
}
