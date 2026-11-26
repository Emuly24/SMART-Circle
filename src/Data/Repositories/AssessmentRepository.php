<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Exams, assignments and exercises, and the questions in each. */
final class AssessmentRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $a */
    public function create(array $a, int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO assessments (kind, title, form, subject, instructions, opens_at, closes_at, duration_min, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$a['kind'], $a['title'], $a['form'], $a['subject'], $a['instructions'], $a['opens_at'], $a['closes_at'], $a['duration_min'], $createdBy]
        );
    }

    /** @param array<string, mixed> $a */
    public function update(int $id, array $a): void
    {
        $this->db->run(
            'UPDATE assessments SET kind = ?, title = ?, form = ?, subject = ?, instructions = ?, opens_at = ?, closes_at = ?, duration_min = ? WHERE id = ?',
            [$a['kind'], $a['title'], $a['form'], $a['subject'], $a['instructions'], $a['opens_at'], $a['closes_at'], $a['duration_min'], $id]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one(
            'SELECT a.*, n.title AS note_title, n.topic_id AS note_topic_id FROM assessments a LEFT JOIN notes n ON n.id = a.note_id
             WHERE a.id = ? AND a.lesson_id IS NULL',
            [$id]
        );
    }

    /** @return list<array<string, mixed>> all, with question and submission counts */
    public function all(): array
    {
        return $this->db->all(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM assessment_questions aq WHERE aq.assessment_id = a.id) AS questions,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assessment_id = a.id AND s.status <> 'in_progress') AS submitted,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assessment_id = a.id AND s.status = 'submitted') AS to_mark
             FROM assessments a WHERE a.lesson_id IS NULL AND a.note_id IS NULL ORDER BY a.created_at DESC, a.id DESC"
        );
    }

    /** @return list<array<string, mixed>> published ones for a form */
    public function publishedForForm(int $form): array
    {
        return $this->db->all(
            "SELECT * FROM assessments WHERE status IN ('published', 'closed') AND form = ? AND lesson_id IS NULL AND note_id IS NULL
             ORDER BY COALESCE(closes_at, '9999-12-31'), created_at DESC",
            [$form]
        );
    }

    /** A checkpoint exercise inside notes. */
    public function createForNote(int $noteId, string $title, int $form, string $subject, int $createdBy): int
    {
        return $this->db->insert(
            "INSERT INTO assessments (kind, title, form, subject, note_id, created_by) VALUES ('note_check', ?, ?, ?, ?, ?)",
            [$title, $form, $subject, $noteId, $createdBy]
        );
    }

    public function setStatusForNote(int $noteId, string $status): void
    {
        $this->db->run('UPDATE assessments SET status = ? WHERE note_id = ?', [$status, $noteId]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE assessments SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function releaseResults(int $id): void
    {
        $this->db->run('UPDATE assessments SET results_at = COALESCE(results_at, NOW()) WHERE id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM assessments WHERE id = ?', [$id]);
    }

    /* ---------- Questions in an assessment ---------- */

    /** @return list<array<string, mixed>> questions in order, with the marks that apply */
    public function questions(int $assessmentId): array
    {
        return array_map([QuestionRepository::class, 'decode'], $this->db->all(
            'SELECT q.*, t.title AS topic_title, aq.sort_order, COALESCE(aq.marks_override, q.marks) AS max_marks
             FROM assessment_questions aq JOIN questions q ON q.id = aq.question_id
             LEFT JOIN topics t ON t.id = q.topic_id
             WHERE aq.assessment_id = ? ORDER BY aq.sort_order, q.id',
            [$assessmentId]
        ));
    }

    public function addQuestion(int $assessmentId, int $questionId): void
    {
        $next = (int) $this->db->scalar('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM assessment_questions WHERE assessment_id = ?', [$assessmentId]);
        $this->db->run(
            'INSERT IGNORE INTO assessment_questions (assessment_id, question_id, sort_order) VALUES (?, ?, ?)',
            [$assessmentId, $questionId, $next]
        );
    }

    public function removeQuestion(int $assessmentId, int $questionId): void
    {
        $this->db->run('DELETE FROM assessment_questions WHERE assessment_id = ? AND question_id = ?', [$assessmentId, $questionId]);
    }

    public function moveQuestion(int $assessmentId, int $questionId, int $direction): void
    {
        $rows = $this->db->all('SELECT question_id FROM assessment_questions WHERE assessment_id = ? ORDER BY sort_order, question_id', [$assessmentId]);
        $ids = array_map(static fn (array $r): int => (int) $r['question_id'], $rows);
        $i = array_search($questionId, $ids, true);
        $j = $i === false ? false : $i + $direction;
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $this->db->transaction(function (Database $db) use ($ids, $assessmentId): void {
            foreach ($ids as $k => $qid) {
                $db->run('UPDATE assessment_questions SET sort_order = ? WHERE assessment_id = ? AND question_id = ?', [($k + 1) * 10, $assessmentId, $qid]);
            }
        });
    }

    /** @return list<int> distinct topics the assessment's questions are about */
    public function topicIds(int $assessmentId): array
    {
        return array_map('intval', array_column($this->db->all(
            'SELECT DISTINCT q.topic_id FROM assessment_questions aq JOIN questions q ON q.id = aq.question_id
             WHERE aq.assessment_id = ? AND q.topic_id IS NOT NULL',
            [$assessmentId]
        ), 'topic_id'));
    }
}
