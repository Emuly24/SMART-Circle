<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Quick 5 attempts, anonymous termly feedback, and the figures for the impact report. */
final class LearningRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /* ---------- Quick 5 ---------- */

    /**
     * Auto-marked questions on topics in the given forms and subjects.
     * @param list<string> $subjects
     * @return list<array<string, mixed>>
     */
    public function candidateQuestions(int $maxForm, array $subjects): array
    {
        if ($subjects === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($subjects), '?'));

        return $this->db->all(
            "SELECT id, form, subject, topic_id, type, prompt, data, marks FROM questions
             WHERE topic_id IS NOT NULL AND form <= ? AND subject IN ($in) AND type IN ('mcq', 'numeric', 'grammar_fill')",
            [$maxForm, ...$subjects]
        );
    }

    /** @return list<int> questions this student answered in the last N days */
    public function recentQuestionIds(int $userId, int $days): array
    {
        return array_map('intval', array_column($this->db->all(
            'SELECT DISTINCT question_id FROM quick5_attempts WHERE user_id = ? AND answered_at > NOW() - INTERVAL ? DAY',
            [$userId, $days]
        ), 'question_id'));
    }

    public function record(int $userId, int $questionId, bool $correct, string $week): void
    {
        $this->db->run('INSERT INTO quick5_attempts (user_id, question_id, correct, week) VALUES (?, ?, ?, ?)', [$userId, $questionId, $correct ? 1 : 0, $week]);
    }

    /** @return array<string, int> ISO week => answers that week */
    public function weeks(int $userId): array
    {
        $out = [];
        foreach ($this->db->all('SELECT week, COUNT(*) AS n FROM quick5_attempts WHERE user_id = ? GROUP BY week', [$userId]) as $r) {
            $out[(string) $r['week']] = (int) $r['n'];
        }

        return $out;
    }

    /** @return array{answered: int, correct: int} */
    public function accuracy(int $userId, int $days): array
    {
        $r = $this->db->one('SELECT COUNT(*) AS answered, COALESCE(SUM(correct), 0) AS correct FROM quick5_attempts WHERE user_id = ? AND answered_at > NOW() - INTERVAL ? DAY', [$userId, $days]);

        return ['answered' => (int) ($r['answered'] ?? 0), 'correct' => (int) ($r['correct'] ?? 0)];
    }

    /* ---------- Termly feedback ---------- */

    public function feedbackDone(int $userId, int $termId): bool
    {
        return $this->db->scalar('SELECT 1 FROM term_feedback_done WHERE user_id = ? AND term_id = ?', [$userId, $termId]) !== null;
    }

    /** Saves a response with no link to the student, and separately notes that they answered. */
    public function saveFeedback(int $userId, int $termId, ?int $form, int $clarity, string $pace, ?string $helpful, ?string $change): void
    {
        $this->db->transaction(function (Database $db) use ($userId, $termId, $form, $clarity, $pace, $helpful, $change): void {
            $db->run('INSERT INTO term_feedback_done (user_id, term_id) VALUES (?, ?)', [$userId, $termId]);
            $db->run('INSERT INTO term_feedback (term_id, form, clarity, pace, helpful, change_one, created_on) VALUES (?, ?, ?, ?, ?, ?, CURDATE())',
                [$termId, $form, $clarity, $pace, $helpful, $change]);
        });
    }

    /** @return list<array<string, mixed>> responses for a term, in random order (never by time) */
    public function feedback(int $termId): array
    {
        return $this->db->all('SELECT form, clarity, pace, helpful, change_one FROM term_feedback WHERE term_id = ? ORDER BY RAND()', [$termId]);
    }

    /* ---------- Impact ---------- */

    /** @return list<array<string, mixed>> active students with their entry quiz result and activity */
    public function impactRows(): array
    {
        return $this->db->all(
            "SELECT u.id, u.full_name, sp.form, sp.track, a.quiz_score, a.quiz_total,
                    (SELECT COUNT(*) FROM lesson_views v WHERE v.user_id = u.id) AS lessons,
                    (SELECT COUNT(*) FROM quick5_attempts q WHERE q.user_id = u.id AND q.answered_at > NOW() - INTERVAL 30 DAY) AS q_answered,
                    (SELECT COALESCE(SUM(q.correct), 0) FROM quick5_attempts q WHERE q.user_id = u.id AND q.answered_at > NOW() - INTERVAL 30 DAY) AS q_correct
             FROM users u JOIN student_profiles sp ON sp.user_id = u.id LEFT JOIN applications a ON a.id = sp.application_id
             WHERE u.role = 'student' AND u.status = 'active' AND sp.status = 'active' AND u.is_practice = 0
             ORDER BY sp.form, u.full_name"
        );
    }
}
