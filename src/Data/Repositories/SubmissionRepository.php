<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Students' attempts and their answers (one attempt per assessment). */
final class SubmissionRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function forStudent(int $assessmentId, int $userId): ?array
    {
        return $this->db->one('SELECT * FROM submissions WHERE assessment_id = ? AND user_id = ?', [$assessmentId, $userId]);
    }

    /** @return array<string, mixed>|null with student and assessment details */
    public function find(int $id): ?array
    {
        return $this->db->one(
            'SELECT s.*, u.full_name, a.title AS assessment_title, a.kind, a.results_at
             FROM submissions s JOIN users u ON u.id = s.user_id JOIN assessments a ON a.id = s.assessment_id WHERE s.id = ?',
            [$id]
        );
    }

    /** Creates the attempt once; starting again returns the existing one. */
    public function start(int $assessmentId, int $userId, ?int $durationMin): void
    {
        $this->db->run(
            'INSERT IGNORE INTO submissions (assessment_id, user_id, started_at, deadline_at)
             VALUES (?, ?, NOW(), IF(? IS NULL, NULL, NOW() + INTERVAL ? MINUTE))',
            [$assessmentId, $userId, $durationMin, $durationMin ?? 0]
        );
    }

    public function isPastDeadline(int $submissionId, int $graceSeconds): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM submissions WHERE id = ? AND deadline_at IS NOT NULL AND NOW() > deadline_at + INTERVAL ? SECOND',
            [$submissionId, $graceSeconds]
        ) !== null;
    }

    public function secondsLeft(int $submissionId): ?int
    {
        $v = $this->db->scalar('SELECT TIMESTAMPDIFF(SECOND, NOW(), deadline_at) FROM submissions WHERE id = ? AND deadline_at IS NOT NULL', [$submissionId]);

        return $v === null ? null : max(0, (int) $v);
    }

    /** @param array<int, string> $answers question id => answer */
    public function saveAnswers(int $submissionId, array $answers): void
    {
        $this->db->transaction(function (Database $db) use ($submissionId, $answers): void {
            foreach ($answers as $qid => $answer) {
                $db->run(
                    'INSERT INTO submission_answers (submission_id, question_id, answer) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE answer = VALUES(answer)',
                    [$submissionId, $qid, $answer]
                );
            }
        });
    }

    /** @return array<int, array<string, mixed>> question id => answer row */
    public function answers(int $submissionId): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM submission_answers WHERE submission_id = ?', [$submissionId]) as $r) {
            $out[(int) $r['question_id']] = $r;
        }

        return $out;
    }

    /** @param array<int, ?float> $scores question id => automatic score (null = tutor marks) */
    public function submit(int $submissionId, array $scores, float $score, float $max, bool $fullyMarked): void
    {
        $this->db->transaction(function (Database $db) use ($submissionId, $scores, $score, $max, $fullyMarked): void {
            foreach ($scores as $qid => $auto) {
                $db->run(
                    'INSERT INTO submission_answers (submission_id, question_id, auto_score) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE auto_score = VALUES(auto_score)',
                    [$submissionId, $qid, $auto]
                );
            }
            $db->run(
                'UPDATE submissions SET submitted_at = NOW(), status = ?, score = ?, max_score = ? WHERE id = ?',
                [$fullyMarked ? 'marked' : 'submitted', $score, $max, $submissionId]
            );
        });
    }

    /**
     * Tutor marks: per question score (overrides the automatic one) and encouraging feedback.
     * @param array<int, array{score: ?float, did_well: string, off_track: string, next_step: string}> $marks
     */
    public function mark(int $submissionId, array $marks, float $total, string $feedback, int $tutorId): void
    {
        $this->db->transaction(function (Database $db) use ($submissionId, $marks, $total, $feedback, $tutorId): void {
            $nul = static fn (string $s): ?string => $s === '' ? null : $s;
            foreach ($marks as $qid => $m) {
                $db->run(
                    'INSERT INTO submission_answers (submission_id, question_id, manual_score, did_well, off_track, next_step) VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE manual_score = VALUES(manual_score), did_well = VALUES(did_well),
                        off_track = VALUES(off_track), next_step = VALUES(next_step)',
                    [$submissionId, $qid, $m['score'], $nul($m['did_well']), $nul($m['off_track']), $nul($m['next_step'])]
                );
            }
            $db->run(
                "UPDATE submissions SET status = 'marked', score = ?, feedback = ?, marked_by = ?, marked_at = NOW() WHERE id = ?",
                [$total, $feedback === '' ? null : $feedback, $tutorId, $submissionId]
            );
        });
    }

    /** A re-check changed one answer's mark: store it and recount the total. */
    public function changeScore(int $submissionId, int $questionId, float $score): void
    {
        $this->db->transaction(function (Database $db) use ($submissionId, $questionId, $score): void {
            $db->run(
                'INSERT INTO submission_answers (submission_id, question_id, manual_score) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE manual_score = VALUES(manual_score)',
                [$submissionId, $questionId, $score]
            );
            $db->run(
                'UPDATE submissions SET score = (SELECT COALESCE(SUM(COALESCE(manual_score, auto_score, 0)), 0)
                 FROM submission_answers WHERE submission_id = ?) WHERE id = ?',
                [$submissionId, $submissionId]
            );
        });
    }

    /** @return list<array<string, mixed>> everything waiting for the tutor, oldest first */
    public function toMark(): array
    {
        return $this->db->all(
            "SELECT s.*, u.full_name, a.title AS assessment_title, a.kind, a.form, a.subject, n.title AS note_title
             FROM submissions s JOIN users u ON u.id = s.user_id JOIN assessments a ON a.id = s.assessment_id
             LEFT JOIN notes n ON n.id = a.note_id
             WHERE s.status = 'submitted' ORDER BY s.submitted_at"
        );
    }

    /** @return list<array<string, mixed>> the student's latest marked work */
    public function recentMarked(int $userId, int $limit = 5): array
    {
        return $this->db->all(
            "SELECT s.*, a.title AS assessment_title, a.kind, a.results_at, a.note_id, n.title AS note_title
             FROM submissions s JOIN assessments a ON a.id = s.assessment_id LEFT JOIN notes n ON n.id = a.note_id
             WHERE s.user_id = ? AND s.status = 'marked'
             ORDER BY COALESCE(s.marked_at, s.submitted_at) DESC LIMIT " . max(1, min(20, $limit)),
            [$userId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function forAssessment(int $assessmentId): array
    {
        return $this->db->all(
            'SELECT s.*, u.full_name FROM submissions s JOIN users u ON u.id = s.user_id
             WHERE s.assessment_id = ? ORDER BY s.status = \'submitted\' DESC, u.full_name',
            [$assessmentId]
        );
    }

    /** @return array<int, array<string, mixed>> assessment id => this student's submission */
    public function allForStudent(int $userId): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM submissions WHERE user_id = ?', [$userId]) as $r) {
            $out[(int) $r['assessment_id']] = $r;
        }

        return $out;
    }

    /** @return list<int> students who submitted */
    public function submitters(int $assessmentId): array
    {
        return array_map('intval', array_column($this->db->all(
            "SELECT user_id FROM submissions WHERE assessment_id = ? AND status <> 'in_progress'",
            [$assessmentId]
        ), 'user_id'));
    }
}
