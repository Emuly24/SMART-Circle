<?php

declare(strict_types=1);

namespace SmartCircle\Application\Messages;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\MessageRepository;

/**
 * In-app messages. Students and partners write only to the tutor (never to each other);
 * the tutor writes to anyone and sends announcements. Nothing can be deleted.
 */
final class MessageService
{
    public const MAX_LENGTH = 4000;
    public const PER_HOUR = 30;
    public const AUDIENCES = ['students' => 'All students', 'form:1' => 'Form 1', 'form:2' => 'Form 2', 'form:3' => 'Form 3', 'form:4' => 'Form 4', 'alumni' => 'All alumni', 'partners' => 'All partner teachers'];

    public function __construct(
        private readonly MessageRepository $messages,
        private readonly NotificationService $notifications,
        /** @var \Closure(): list<array<string, mixed>> active students with user_id and form */
        private readonly \Closure $students,
    ) {
    }

    /** Checks a message body; returns an error or null. */
    public static function checkBody(string $body): ?string
    {
        $body = trim($body);
        if ($body === '') {
            return 'Write a message first.';
        }
        if (mb_strlen($body) > self::MAX_LENGTH) {
            return 'Keep a message under ' . self::MAX_LENGTH . ' characters (split it into two).';
        }

        return null;
    }

    /** Who a person may start a conversation with. @return list<array<string, mixed>> */
    public function recipients(int $userId, string $role): array
    {
        if ($role === 'tutor') {
            $out = [];
            foreach (['student', 'alumni', 'partner', 'tutor'] as $r) {
                foreach ($this->messages->people($r) as $p) {
                    if ((int) $p['id'] !== $userId) {
                        $out[] = $p + ['role' => $r];
                    }
                }
            }

            return $out;
        }

        return array_map(static fn (array $p): array => $p + ['role' => 'tutor'], $this->messages->people('tutor'));
    }

    /**
     * Start a direct conversation.
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function start(int $userId, string $role, int $toId, string $subject, string $body): array
    {
        $errors = [];
        $allowed = array_column($this->recipients($userId, $role), 'role', 'id');
        if (!isset($allowed[$toId])) {
            $errors['to'] = $role === 'tutor' ? 'Choose who to write to.' : 'You can write to the tutor.';
        }
        $subject = trim($subject);
        if ($subject === '' || mb_strlen($subject) > 150) {
            $errors['subject'] = 'Give it a short subject (up to 150 characters).';
        }
        if (($e = self::checkBody($body)) !== null) {
            $errors['body'] = $e;
        }
        if ($errors === [] && ($e = $this->flood($userId)) !== null) {
            $errors['body'] = $e;
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $id = $this->messages->createConversation('direct', $subject, $userId, [$userId, $toId]);
        $this->messages->addMessage($id, $userId, trim($body));
        $this->notifications->notify([$toId], 'message', 'New message: ' . $subject, null, '/messages/' . $id);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * Send an announcement (tutor only). Members are fixed when it is sent.
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function announce(int $tutorId, string $audience, string $subject, string $body): array
    {
        $errors = [];
        if (!isset(self::AUDIENCES[$audience])) {
            $errors['audience'] = 'Choose who should get it.';
        }
        $subject = trim($subject);
        if ($subject === '' || mb_strlen($subject) > 150) {
            $errors['subject'] = 'Give it a short subject (up to 150 characters).';
        }
        if (($e = self::checkBody($body)) !== null) {
            $errors['body'] = $e;
        }
        $members = $errors === [] ? $this->audienceIds($audience) : [];
        if ($errors === [] && $members === []) {
            $errors['audience'] = 'Nobody is in that group yet.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $id = $this->messages->createConversation('announcement', $subject, $tutorId, [$tutorId, ...$members], $audience);
        $this->messages->addMessage($id, $tutorId, trim($body));
        $this->notifications->notify($members, 'announcement', 'Announcement: ' . $subject, null, '/messages/' . $id);

        return ['errors' => [], 'id' => $id];
    }

    /** @return list<int> */
    private function audienceIds(string $audience): array
    {
        if ($audience === 'partners' || $audience === 'alumni') {
            return array_map(static fn (array $p): int => (int) $p['id'], $this->messages->people($audience === 'partners' ? 'partner' : 'alumni'));
        }
        $form = str_starts_with($audience, 'form:') ? (int) substr($audience, 5) : null;
        $ids = [];
        foreach (($this->students)() as $s) {
            if ($form === null || (int) $s['form'] === $form) {
                $ids[] = (int) $s['user_id'];
            }
        }

        return $ids;
    }

    /**
     * Open a conversation for reading (marks it read). Null if the person may not see it.
     * @return array<string, mixed>|null
     */
    public function open(int $conversationId, int $userId, string $role): ?array
    {
        $c = $this->messages->conversation($conversationId);
        if ($c === null || !$this->canSee($c, $userId, $role)) {
            return null;
        }
        $members = $this->messages->members($conversationId);
        $messages = $this->messages->messages($conversationId);
        if ($this->messages->isMember($conversationId, $userId)) {
            $this->messages->markRead($conversationId, $userId);
        }

        return [
            'conversation' => $c,
            'members' => $members,
            'messages' => $messages,
            'seen' => self::seenBy($messages, $members, $userId),
            'can_reply' => $this->canReply($c, $userId, $role),
        ];
    }

    /**
     * For direct and support conversations: has the other side read my latest message?
     * @param list<array<string, mixed>> $messages @param list<array<string, mixed>> $members
     * @return array{at: string, names: list<string>}|null
     */
    public static function seenBy(array $messages, array $members, int $userId): ?array
    {
        $mine = null;
        foreach ($messages as $m) {
            if ((int) $m['user_id'] === $userId) {
                $mine = $m;
            }
        }
        if ($mine === null || count($members) > 2) {
            return null;
        }
        $names = [];
        $at = '';
        foreach ($members as $p) {
            if ((int) $p['user_id'] !== $userId && $p['last_read_at'] !== null && (string) $p['last_read_at'] >= (string) $mine['created_at']) {
                $names[] = (string) $p['full_name'];
                $at = (string) $p['last_read_at'];
            }
        }

        return $names === [] ? null : ['at' => $at, 'names' => $names];
    }

    /** @param array<string, mixed> $c */
    private function canSee(array $c, int $userId, string $role): bool
    {
        if ($this->messages->isMember((int) $c['id'], $userId)) {
            return true;
        }

        // Any tutor can read support rooms and everything else; never partners or other students.
        return $role === 'tutor';
    }

    /** @param array<string, mixed> $c */
    private function canReply(array $c, int $userId, string $role): bool
    {
        if ($c['kind'] === 'announcement') {
            return $role === 'tutor';
        }

        return $role === 'tutor' || $this->messages->isMember((int) $c['id'], $userId);
    }

    /** @return string|null an error, or null when sent */
    public function reply(int $conversationId, int $userId, string $role, string $body): ?string
    {
        $c = $this->messages->conversation($conversationId);
        if ($c === null || !$this->canSee($c, $userId, $role) || !$this->canReply($c, $userId, $role)) {
            return 'You cannot reply here.';
        }
        if (($e = self::checkBody($body)) !== null || ($e = $this->flood($userId)) !== null) {
            return $e;
        }
        if (!$this->messages->isMember($conversationId, $userId)) {
            // A tutor joining a conversation (e.g. a second tutor) becomes a member.
            $this->messages->addMember($conversationId, $userId);
        }
        $this->messages->addMessage($conversationId, $userId, trim($body));
        $others = array_values(array_filter(
            array_map(static fn (array $m): int => (int) $m['user_id'], $this->messages->members($conversationId)),
            static fn (int $id): bool => $id !== $userId
        ));
        $link = $c['kind'] === 'support' ? ($role === 'tutor' ? '/support' : '/studio/support/' . $conversationId) : '/messages/' . $conversationId;
        $title = $c['kind'] === 'support' ? 'New message in your private room' : 'New reply: ' . $c['subject'];
        if ($c['kind'] === 'support' && $role !== 'tutor') {
            $title = 'Private room: new message from ' . ($c['student_name'] ?? 'a student');
        }
        $this->notifications->notify($others, $c['kind'] === 'support' ? 'support' : 'message', $title, null, $link);

        return null;
    }

    private function flood(int $userId): ?string
    {
        return $this->messages->sentInLastHour($userId) >= self::PER_HOUR
            ? 'You have sent a lot of messages in the last hour. Please wait a little before sending more.'
            : null;
    }

    /** @return list<array<string, mixed>> active alumni */
    public function alumni(): array
    {
        return $this->messages->people('alumni');
    }

    /** @return list<array<string, mixed>> */
    public function inbox(int $userId): array
    {
        return array_values(array_filter($this->messages->inbox($userId), static fn (array $c): bool => $c['kind'] !== 'support'));
    }

    public function unread(int $userId): int
    {
        return $this->messages->unreadConversations($userId);
    }
}
