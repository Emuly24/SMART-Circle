<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Conversations, their members and messages. There is deliberately no way to delete a message. */
final class MessageRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param list<int> $memberIds */
    public function createConversation(string $kind, string $subject, int $createdBy, array $memberIds, ?string $audience = null, ?int $studentId = null, ?string $openedBy = null): int
    {
        return $this->db->transaction(function (Database $db) use ($kind, $subject, $createdBy, $memberIds, $audience, $studentId, $openedBy): int {
            $id = $db->insert(
                'INSERT INTO conversations (kind, subject, audience, student_id, opened_by, created_by) VALUES (?, ?, ?, ?, ?, ?)',
                [$kind, $subject, $audience, $studentId, $openedBy, $createdBy]
            );
            foreach (array_unique($memberIds) as $uid) {
                $db->run('INSERT IGNORE INTO conversation_members (conversation_id, user_id) VALUES (?, ?)', [$id, $uid]);
            }

            return $id;
        });
    }

    public function addMessage(int $conversationId, int $userId, string $body, ?int $quoteId = null): int
    {
        // A quote must be an earlier message in the same conversation; anything else is dropped.
        if ($quoteId !== null && $this->db->scalar('SELECT 1 FROM messages WHERE id = ? AND conversation_id = ?', [$quoteId, $conversationId]) === null) {
            $quoteId = null;
        }
        $id = $this->db->insert('INSERT INTO messages (conversation_id, user_id, body, quote_id) VALUES (?, ?, ?, ?)', [$conversationId, $userId, $body, $quoteId]);
        $this->db->run('UPDATE conversations SET last_message_at = NOW() WHERE id = ?', [$conversationId]);
        $this->markRead($conversationId, $userId);

        return $id;
    }

    public function addMember(int $conversationId, int $userId): void
    {
        $this->db->run('INSERT IGNORE INTO conversation_members (conversation_id, user_id) VALUES (?, ?)', [$conversationId, $userId]);
    }

    public function markRead(int $conversationId, int $userId): void
    {
        $this->db->run('UPDATE conversation_members SET last_read_at = NOW() WHERE conversation_id = ? AND user_id = ?', [$conversationId, $userId]);
    }

    /** @return array<string, mixed>|null */
    public function conversation(int $id): ?array
    {
        return $this->db->one(
            'SELECT c.*, s.full_name AS student_name FROM conversations c LEFT JOIN users s ON s.id = c.student_id WHERE c.id = ?',
            [$id]
        );
    }

    public function isMember(int $conversationId, int $userId): bool
    {
        return $this->db->scalar('SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?', [$conversationId, $userId]) !== null;
    }

    /** @return list<array<string, mixed>> members with names, roles and when they last read */
    public function members(int $conversationId): array
    {
        return $this->db->all(
            'SELECT m.user_id, m.last_read_at, u.full_name, u.role FROM conversation_members m JOIN users u ON u.id = m.user_id
             WHERE m.conversation_id = ? ORDER BY u.full_name',
            [$conversationId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function messages(int $conversationId): array
    {
        return $this->db->all(
            'SELECT msg.*, u.full_name, u.role, u.avatar_at, q.body AS quote_body, qu.full_name AS quote_name, q.user_id AS quote_user_id
             FROM messages msg LEFT JOIN users u ON u.id = msg.user_id
             LEFT JOIN messages q ON q.id = msg.quote_id LEFT JOIN users qu ON qu.id = q.user_id
             WHERE msg.conversation_id = ? ORDER BY msg.created_at, msg.id',
            [$conversationId]
        );
    }

    /** @return list<array<string, mixed>> a user's conversations, newest first, with unread flags */
    public function inbox(int $userId, ?string $kind = null): array
    {
        return $this->db->all(
            "SELECT c.*, s.full_name AS student_name, m.last_read_at,
                    (SELECT COUNT(*) FROM messages x WHERE x.conversation_id = c.id) AS message_count,
                    (SELECT x.body FROM messages x WHERE x.conversation_id = c.id ORDER BY x.created_at DESC, x.id DESC LIMIT 1) AS last_body,
                    (SELECT x.user_id FROM messages x WHERE x.conversation_id = c.id ORDER BY x.created_at DESC, x.id DESC LIMIT 1) AS last_author,
                    (SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ') FROM conversation_members o JOIN users u ON u.id = o.user_id
                      WHERE o.conversation_id = c.id AND o.user_id <> ?) AS others,
                    (SELECT COUNT(*) FROM messages x WHERE x.conversation_id = c.id AND x.user_id <> ?
                      AND x.created_at > COALESCE(m.last_read_at, '1000-01-01')) AS unread
             FROM conversations c JOIN conversation_members m ON m.conversation_id = c.id AND m.user_id = ?
             LEFT JOIN users s ON s.id = c.student_id"
            . ($kind !== null ? ' WHERE c.kind = ?' : '') . ' ORDER BY c.last_message_at DESC, c.id DESC LIMIT 200',
            $kind !== null ? [$userId, $userId, $userId, $kind] : [$userId, $userId, $userId]
        );
    }

    public function unreadConversations(int $userId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(DISTINCT c.id) FROM conversations c JOIN conversation_members m ON m.conversation_id = c.id AND m.user_id = ?
             JOIN messages x ON x.conversation_id = c.id AND x.user_id <> ? AND x.created_at > COALESCE(m.last_read_at, '1000-01-01')",
            [$userId, $userId]
        );
    }

    public function sentInLastHour(int $userId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM messages WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$userId]);
    }

    /** @return array<string, mixed>|null the student's support room */
    public function supportRoom(int $studentId): ?array
    {
        return $this->db->one("SELECT * FROM conversations WHERE kind = 'support' AND student_id = ? ORDER BY id DESC LIMIT 1", [$studentId]);
    }

    /** @return list<array<string, mixed>> every support room, newest activity first */
    public function supportRooms(): array
    {
        return $this->db->all(
            "SELECT c.*, s.full_name AS student_name, (SELECT COUNT(*) FROM messages x WHERE x.conversation_id = c.id) AS message_count
             FROM conversations c JOIN users s ON s.id = c.student_id WHERE c.kind = 'support' ORDER BY c.last_message_at DESC"
        );
    }

    public function markGuardianNotified(int $conversationId): void
    {
        $this->db->run('UPDATE conversations SET guardian_notified_at = COALESCE(guardian_notified_at, NOW()) WHERE id = ?', [$conversationId]);
    }

    /** @return list<array<string, mixed>> active users who may receive messages, by role */
    public function people(string $role): array
    {
        if ($role === 'team') {
            return $this->db->all("SELECT u.id, u.full_name FROM users u JOIN team_profiles t ON t.user_id = u.id
                                   WHERE u.role = 'team' AND u.status = 'active' AND t.status = 'active' ORDER BY u.full_name");
        }

        return $this->db->all("SELECT id, full_name FROM users WHERE role = ? AND status = 'active' ORDER BY full_name", [$role]);
    }
}
