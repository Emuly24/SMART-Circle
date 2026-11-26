<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Students' requests to re-check a mark, with the full history kept. */
final class RecheckRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(int $submissionId, int $questionId, int $userId, string $reason, ?float $oldScore): int
    {
        return $this->db->insert(
            'INSERT INTO recheck_requests (submission_id, question_id, user_id, reason, old_score) VALUES (?, ?, ?, ?, ?)',
            [$submissionId, $questionId, $userId, $reason, $oldScore]
        );
    }

    public function hasOpen(int $submissionId, int $questionId): bool
    {
        return $this->db->scalar(
            "SELECT 1 FROM recheck_requests WHERE submission_id = ? AND question_id = ? AND status = 'open'",
            [$submissionId, $questionId]
        ) !== null;
    }

    /** @return array<string, mixed>|null with student, assessment and question details */
    public function find(int $id): ?array
    {
        return $this->db->one(
            'SELECT r.*, u.full_name, a.id AS assessment_id, a.title AS assessment_title, q.prompt, q.type, q.data,
                    COALESCE(aq.marks_override, q.marks) AS max_marks, sa.answer, sa.auto_score, sa.manual_score
             FROM recheck_requests r
             JOIN users u ON u.id = r.user_id
             JOIN submissions s ON s.id = r.submission_id
             JOIN assessments a ON a.id = s.assessment_id
             JOIN questions q ON q.id = r.question_id
             LEFT JOIN assessment_questions aq ON aq.assessment_id = a.id AND aq.question_id = q.id
             LEFT JOIN submission_answers sa ON sa.submission_id = r.submission_id AND sa.question_id = r.question_id
             WHERE r.id = ?',
            [$id]
        );
    }

    /** @return list<array<string, mixed>> open first, then the most recent decisions */
    public function all(): array
    {
        return $this->db->all(
            "SELECT r.*, u.full_name, a.title AS assessment_title, q.prompt
             FROM recheck_requests r
             JOIN users u ON u.id = r.user_id
             JOIN submissions s ON s.id = r.submission_id
             JOIN assessments a ON a.id = s.assessment_id
             JOIN questions q ON q.id = r.question_id
             ORDER BY r.status = 'open' DESC, r.created_at DESC LIMIT 200"
        );
    }

    /** @return array<int, list<array<string, mixed>>> question id => requests, oldest first */
    public function forSubmission(int $submissionId): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM recheck_requests WHERE submission_id = ? ORDER BY created_at, id', [$submissionId]) as $r) {
            $out[(int) $r['question_id']][] = $r;
        }

        return $out;
    }

    public function countOpen(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM recheck_requests WHERE status = 'open'");
    }

    public function resolve(int $id, string $status, ?float $newScore, string $reply, int $tutorId): void
    {
        $this->db->run(
            'UPDATE recheck_requests SET status = ?, new_score = ?, tutor_reply = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?',
            [$status, $newScore, $reply, $tutorId, $id]
        );
    }
}
