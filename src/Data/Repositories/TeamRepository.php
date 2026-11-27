<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** The SMART Circle Team: volunteer tutors and the subjects they teach. */
final class TeamRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(int $userId, string $subjects, int $invitedBy): void
    {
        $this->db->run('INSERT INTO team_profiles (user_id, subjects, invited_by) VALUES (?, ?, ?)', [$userId, $subjects, $invitedBy]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId): ?array
    {
        return $this->db->one(
            'SELECT t.*, u.full_name, u.phone, u.email, u.status AS user_status, u.last_login_at, u.is_practice
             FROM team_profiles t JOIN users u ON u.id = t.user_id WHERE t.user_id = ?',
            [$userId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(
            'SELECT t.*, u.full_name, u.phone, u.email, u.status AS user_status, u.last_login_at
             FROM team_profiles t JOIN users u ON u.id = t.user_id WHERE u.is_practice = 0 ORDER BY t.status, u.full_name'
        );
    }

    public function agree(int $userId, string $version): void
    {
        $this->db->run("UPDATE team_profiles SET status = 'active', agreement_version = ?, agreed_at = NOW(), declined_at = NULL WHERE user_id = ?", [$version, $userId]);
    }

    public function setStatus(int $userId, string $status): void
    {
        $this->db->run(
            'UPDATE team_profiles SET status = ?' . ($status === 'declined' ? ', declined_at = NOW()' : '') . ' WHERE user_id = ?',
            [$status, $userId]
        );
    }

    public function setSubjects(int $userId, string $subjects): void
    {
        $this->db->run('UPDATE team_profiles SET subjects = ? WHERE user_id = ?', [$subjects, $userId]);
    }

    public function setUserStatus(int $userId, string $status): void
    {
        $this->db->run('UPDATE users SET status = ? WHERE id = ?', [$status, $userId]);
    }

    /**
     * The subject and creator of an item, for checking a team tutor may open it.
     * @return array{subject: ?string, owner: ?int}|null
     */
    public function subjectOf(string $type, int $id): ?array
    {
        $sql = match ($type) {
            'lesson' => 'SELECT subject, created_by AS owner FROM lessons WHERE id = ?',
            'note' => 'SELECT subject, created_by AS owner FROM notes WHERE id = ?',
            'question' => 'SELECT subject, created_by AS owner FROM questions WHERE id = ?',
            'assessment' => 'SELECT subject, created_by AS owner FROM assessments WHERE id = ?',
            'submission' => 'SELECT a.subject, a.created_by AS owner FROM submissions s JOIN assessments a ON a.id = s.assessment_id WHERE s.id = ?',
            'recheck' => 'SELECT a.subject, a.created_by AS owner FROM recheck_requests r JOIN submissions s ON s.id = r.submission_id
                          JOIN assessments a ON a.id = s.assessment_id WHERE r.id = ?',
            'request' => 'SELECT t.subject, NULL AS owner FROM topic_requests r JOIN topics t ON t.id = r.topic_id WHERE r.id = ?',
            'upload' => 'SELECT NULL AS subject, user_id AS owner FROM lesson_uploads WHERE id = ?',
            default => null,
        };
        if ($sql === null) {
            return null;
        }
        $row = $this->db->one($sql, [$id]);

        return $row === null ? null : ['subject' => $row['subject'] !== null ? (string) $row['subject'] : null, 'owner' => $row['owner'] !== null ? (int) $row['owner'] : null];
    }
}
