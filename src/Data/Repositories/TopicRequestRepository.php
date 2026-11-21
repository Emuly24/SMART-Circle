<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Topic requests (one per topic) and the classmates' "me too" votes. */
final class TopicRequestRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function forTopic(int $topicId): ?array
    {
        return $this->db->one('SELECT * FROM topic_requests WHERE topic_id = ?', [$topicId]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one(
            'SELECT r.*, t.title AS topic_title, t.form, t.subject, t.parent_id, p.title AS parent_title
             FROM topic_requests r JOIN topics t ON t.id = r.topic_id LEFT JOIN topics p ON p.id = t.parent_id WHERE r.id = ?',
            [$id]
        );
    }

    public function create(int $topicId, int $userId, string $struggleType, string $details, bool $isException, ?string $exceptionReason): int
    {
        return $this->db->insert(
            'INSERT INTO topic_requests (topic_id, user_id, struggle_type, details, is_exception, exception_reason, exception_status)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$topicId, $userId, $struggleType, $details, $isException ? 1 : 0, $exceptionReason, $isException ? 'pending' : null]
        );
    }

    public function vote(int $requestId, int $userId, string $struggleType, ?string $details): void
    {
        $this->db->run(
            'INSERT INTO topic_request_votes (request_id, user_id, struggle_type, details) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE struggle_type = VALUES(struggle_type), details = VALUES(details)',
            [$requestId, $userId, $struggleType, $details]
        );
    }

    public function hasJoined(int $requestId, int $userId): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM topic_requests WHERE id = ? AND user_id = ?
             UNION SELECT 1 FROM topic_request_votes WHERE request_id = ? AND user_id = ?',
            [$requestId, $userId, $requestId, $userId]
        ) !== null;
    }

    /** A covered-at-my-school vote turns an exception request into a normal one. */
    public function clearException(int $requestId): void
    {
        $this->db->run("UPDATE topic_requests SET is_exception = 0, exception_status = 'approved' WHERE id = ?", [$requestId]);
    }

    public function setException(int $requestId, string $status): void
    {
        $this->db->run('UPDATE topic_requests SET exception_status = ? WHERE id = ?', [$status, $requestId]);
    }

    public function setStatus(int $requestId, string $status, ?int $lessonId = null): void
    {
        $this->db->run(
            'UPDATE topic_requests SET status = ?, lesson_id = COALESCE(?, lesson_id) WHERE id = ?',
            [$status, $lessonId, $requestId]
        );
    }

    /**
     * Everyone's struggles on a request: the requester first, then the votes.
     * @return list<array{user_id: int, full_name: string, struggle_type: string, details: ?string, created_at: string}>
     */
    public function voices(int $requestId): array
    {
        return array_map(static fn (array $r): array => [
            'user_id' => (int) $r['user_id'],
            'full_name' => (string) $r['full_name'],
            'struggle_type' => (string) $r['struggle_type'],
            'details' => $r['details'],
            'created_at' => (string) $r['created_at'],
        ], $this->db->all(
            'SELECT x.*, u.full_name FROM (
                SELECT user_id, struggle_type, details, created_at, 0 AS ord FROM topic_requests WHERE id = ?
                UNION ALL
                SELECT user_id, struggle_type, details, created_at, 1 AS ord FROM topic_request_votes WHERE request_id = ?
             ) x JOIN users u ON u.id = x.user_id
             ORDER BY x.ord, x.created_at',
            [$requestId, $requestId]
        ));
    }

    /** @return list<int> requester and voters, for notifications */
    public function participants(int $requestId): array
    {
        return array_map(static fn (array $r): int => (int) $r['user_id'], $this->db->all(
            'SELECT user_id FROM topic_requests WHERE id = ? UNION SELECT user_id FROM topic_request_votes WHERE request_id = ?',
            [$requestId, $requestId]
        ));
    }

    /**
     * Tutor's ranked list.
     * @param array{form?: int, subject?: string, status?: string} $filter
     * @return list<array<string, mixed>>
     */
    public function ranked(array $filter): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filter['form'])) {
            $where[] = 't.form = ?';
            $params[] = $filter['form'];
        }
        if (!empty($filter['subject'])) {
            $where[] = 't.subject = ?';
            $params[] = $filter['subject'];
        }
        if (($filter['status'] ?? '') === 'active') {
            $where[] = "r.status IN ('open', 'planned')";
        } elseif (!empty($filter['status'])) {
            $where[] = 'r.status = ?';
            $params[] = $filter['status'];
        }

        return $this->db->all(
            'SELECT r.*, t.title AS topic_title, t.form, t.subject, t.unit, p.title AS parent_title,
                    1 + (SELECT COUNT(*) FROM topic_request_votes v WHERE v.request_id = r.id) AS votes
             FROM topic_requests r
             JOIN topics t ON t.id = r.topic_id
             LEFT JOIN topics p ON p.id = t.parent_id
             WHERE ' . implode(' AND ', $where) . "
             ORDER BY (r.exception_status = 'pending') DESC, votes DESC, r.created_at",
            $params
        );
    }

    /** @return array<int, array<string, int>> request id => struggle type => count */
    public function struggleBreakdown(): array
    {
        $out = [];
        $rows = $this->db->all(
            'SELECT id AS request_id, struggle_type, COUNT(*) AS n FROM topic_requests GROUP BY id, struggle_type
             UNION ALL
             SELECT request_id, struggle_type, COUNT(*) AS n FROM topic_request_votes GROUP BY request_id, struggle_type'
        );
        foreach ($rows as $r) {
            $out[(int) $r['request_id']][(string) $r['struggle_type']] = ($out[(int) $r['request_id']][(string) $r['struggle_type']] ?? 0) + (int) $r['n'];
        }

        return $out;
    }

    /** @return list<int> open/planned requests on a topic or its subtopics (for linking a lesson) */
    public function openForTopicTree(int $topicId): array
    {
        return array_map('intval', array_column($this->db->all(
            "SELECT r.id FROM topic_requests r JOIN topics t ON t.id = r.topic_id
             WHERE r.status IN ('open', 'planned') AND (t.id = ? OR t.parent_id = ? OR t.id = (SELECT parent_id FROM topics WHERE id = ?))",
            [$topicId, $topicId, $topicId]
        ), 'id'));
    }
}
