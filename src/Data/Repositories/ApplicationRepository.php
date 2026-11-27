<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Applications, the schools they come from, and the entry quiz. */
final class ApplicationRepository
{
    private const COLUMNS = [
        'intake_id', 'reference', 'full_name', 'gender', 'form', 'track', 'school_id', 'phone', 'email',
        'subjects_taken', 'help_subjects', 'struggling_topics', 'motivation', 'answers', 'device', 'data_access',
        'guardian_name', 'guardian_phone', 'guardian_whatsapp', 'guardian_email', 'guardian_language', 'guardian_relationship', 'guardian_consent_at', 'source', 'ip',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /* ---------- Schools ---------- */

    public function schoolId(string $name, string $district): int
    {
        $id = $this->db->scalar('SELECT id FROM schools WHERE name = ? AND district = ?', [$name, $district]);
        if ($id !== null) {
            return (int) $id;
        }

        return $this->db->insert('INSERT INTO schools (name, district) VALUES (?, ?)', [$name, $district]);
    }

    /* ---------- Applications ---------- */

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data = array_intersect_key($data, array_flip(self::COLUMNS));
        $cols = implode(', ', array_keys($data));
        $marks = implode(', ', array_fill(0, count($data), '?'));

        return $this->db->insert("INSERT INTO applications ($cols) VALUES ($marks)", array_values($data));
    }

    /** @return array<string, mixed>|null with school name/district */
    public function find(int $id): ?array
    {
        return $this->db->one(
            'SELECT a.*, s.name AS school_name, s.district FROM applications a
             LEFT JOIN schools s ON s.id = a.school_id WHERE a.id = ?',
            [$id]
        );
    }

    /** @return array<string, mixed>|null */
    public function findByReference(string $reference): ?array
    {
        $id = $this->db->scalar('SELECT id FROM applications WHERE reference = ?', [$reference]);

        return $id === null ? null : $this->find((int) $id);
    }

    public function referenceExists(string $reference): bool
    {
        return $this->db->scalar('SELECT 1 FROM applications WHERE reference = ?', [$reference]) !== null;
    }

    public function phoneHasApplication(int $intakeId, string $phone): bool
    {
        return $this->db->scalar(
            "SELECT 1 FROM applications WHERE intake_id = ? AND phone = ? AND status <> 'withdrawn'",
            [$intakeId, $phone]
        ) !== null;
    }

    public function countFromIpSince(string $ip, int $hours): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM applications WHERE ip = ? AND created_at > NOW() - INTERVAL ? HOUR',
            [$ip, $hours]
        );
    }

    public function setReport(int $id, string $path, string $mime): void
    {
        $this->db->run('UPDATE applications SET report_path = ?, report_mime = ? WHERE id = ?', [$path, $mime, $id]);
    }

    /**
     * @param array{status?: string, form?: int} $filter
     * @return list<array<string, mixed>>
     */
    public function forIntake(int $intakeId, array $filter = []): array
    {
        $sql = 'SELECT a.*, s.name AS school_name, s.district FROM applications a
                LEFT JOIN schools s ON s.id = a.school_id WHERE a.intake_id = ?';
        $params = [$intakeId];
        if (!empty($filter['status'])) {
            $sql .= ' AND a.status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['form'])) {
            $sql .= ' AND a.form = ?';
            $params[] = $filter['form'];
        }
        $sql .= " ORDER BY FIELD(a.status, 'submitted', 'shortlisted', 'waitlisted', 'admitted', 'declined', 'withdrawn'), a.form, a.created_at";

        return $this->db->all($sql, $params);
    }

    /** @return list<array{form: int, gender: string, status: string, n: int}> */
    public function seatCounts(int $intakeId): array
    {
        return array_map(
            static fn (array $r): array => ['form' => (int) $r['form'], 'gender' => (string) $r['gender'], 'status' => (string) $r['status'], 'n' => (int) $r['n']],
            $this->db->all(
                'SELECT form, gender, status, COUNT(*) AS n FROM applications WHERE intake_id = ? GROUP BY form, gender, status',
                [$intakeId]
            )
        );
    }

    public function decide(int $id, string $status, ?string $note, int $tutorId, ?int $userId = null): void
    {
        $this->db->run(
            'UPDATE applications SET status = ?, tutor_note = COALESCE(?, tutor_note), decided_by = ?, decided_at = NOW(),
             user_id = COALESCE(?, user_id) WHERE id = ?',
            [$status, $note, $tutorId, $userId, $id]
        );
    }

    /* ---------- Entry quiz ---------- */

    /**
     * @param list<string> $subjects
     * @return list<array<string, mixed>>
     */
    public function quizQuestions(int $form, array $subjects): array
    {
        if ($subjects === []) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($subjects), '?'));

        return $this->db->all(
            "SELECT * FROM entry_quiz_questions WHERE form = ? AND is_active = 1 AND subject IN ($marks) ORDER BY sort_order, id",
            [$form, ...$subjects]
        );
    }

    public function startQuiz(int $id): void
    {
        $this->db->run('UPDATE applications SET quiz_started_at = COALESCE(quiz_started_at, NOW()) WHERE id = ?', [$id]);
    }

    /** @param list<array{question_id: int, chosen: ?string, is_correct: bool}> $answers */
    public function saveQuiz(int $id, array $answers, int $score, int $total): void
    {
        $this->db->transaction(function (Database $db) use ($id, $answers, $score, $total): void {
            foreach ($answers as $a) {
                $db->run(
                    'INSERT INTO entry_quiz_answers (application_id, question_id, chosen, is_correct) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE chosen = VALUES(chosen), is_correct = VALUES(is_correct)',
                    [$id, $a['question_id'], $a['chosen'], $a['is_correct'] ? 1 : 0]
                );
            }
            $db->run(
                'UPDATE applications SET quiz_score = ?, quiz_total = ?, quiz_finished_at = NOW() WHERE id = ?',
                [$score, $total, $id]
            );
        });
    }

    /** @return list<array<string, mixed>> the applicant's answers with the questions */
    public function quizAnswers(int $id): array
    {
        return $this->db->all(
            'SELECT q.subject, q.question, q.correct, a.chosen, a.is_correct FROM entry_quiz_answers a
             JOIN entry_quiz_questions q ON q.id = a.question_id WHERE a.application_id = ? ORDER BY q.sort_order',
            [$id]
        );
    }
}
