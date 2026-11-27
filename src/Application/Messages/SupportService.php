<?php

declare(strict_types=1);

namespace SmartCircle\Application\Messages;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\MessageRepository;

/**
 * The private room: one kept, undeletable conversation per student with the tutor, for things that
 * affect their studies. The student can open it; the tutor can invite them (guardians are told an
 * invitation was made, never what is said). Partners never see it.
 */
final class SupportService
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly MessageService $service,
        private readonly NotificationService $notifications,
    ) {
    }

    /** @return array<string, mixed>|null the student's room with its messages */
    public function room(int $studentId): ?array
    {
        $room = $this->messages->supportRoom($studentId);

        return $room === null ? null : $this->service->open((int) $room['id'], $studentId, 'student');
    }

    /** The student opens the room with a first message. @return string|null error */
    public function openByStudent(int $studentId, string $studentName, string $body): ?string
    {
        if (($e = MessageService::checkBody($body)) !== null) {
            return $e;
        }
        $room = $this->messages->supportRoom($studentId);
        if ($room !== null) {
            return $this->service->reply((int) $room['id'], $studentId, 'student', $body);
        }
        $tutors = array_map(static fn (array $t): int => (int) $t['id'], $this->messages->people('tutor'));
        $id = $this->messages->createConversation('support', 'Private room', $studentId, [$studentId, ...$tutors], null, $studentId, 'student');
        $this->messages->addMessage($id, $studentId, trim($body));
        $this->notifications->notify($tutors, 'support', 'Private room: ' . $studentName . ' would like to talk', null, '/studio/support/' . $id);

        return null;
    }

    /**
     * The tutor invites a student into the room with a first message.
     * @return array{error: string|null, id: int|null}
     */
    public function invite(int $tutorId, int $studentId, string $body): array
    {
        if (($e = MessageService::checkBody($body)) !== null) {
            return ['error' => $e, 'id' => null];
        }
        $room = $this->messages->supportRoom($studentId);
        if ($room !== null) {
            $e = $this->service->reply((int) $room['id'], $tutorId, 'tutor', $body);

            return ['error' => $e, 'id' => (int) $room['id']];
        }
        $tutors = array_map(static fn (array $t): int => (int) $t['id'], $this->messages->people('tutor'));
        $id = $this->messages->createConversation('support', 'Private room', $tutorId, [$studentId, ...$tutors], null, $studentId, 'tutor');
        $this->messages->addMessage($id, $tutorId, trim($body));
        $this->notifications->notify([$studentId], 'support', 'Your tutor has invited you to talk privately', null, '/support');

        return ['error' => null, 'id' => $id];
    }

    /** @return list<array<string, mixed>> */
    public function rooms(): array
    {
        return $this->messages->supportRooms();
    }

    /**
     * The room for the admin, or for a team tutor the admin has added to it.
     * @return array<string, mixed>|null
     */
    public function forTutor(int $conversationId, int $tutorId, string $role = 'tutor'): ?array
    {
        $c = $this->messages->conversation($conversationId);
        if ($c === null || $c['kind'] !== 'support') {
            return null;
        }

        return $this->service->open($conversationId, $tutorId, $role);
    }

    /** A team tutor added to the room replies. @return string|null error */
    public function replyAsTeam(int $conversationId, int $userId, string $body): ?string
    {
        return $this->service->reply($conversationId, $userId, 'team', $body);
    }

    /** The admin adds a team tutor to a student's room (the student sees who is in it). */
    public function addMember(int $conversationId, int $userId): bool
    {
        $c = $this->messages->conversation($conversationId);
        $isTeam = in_array($userId, array_map(static fn (array $p): int => (int) $p['id'], $this->messages->people('team')), true);
        if ($c === null || $c['kind'] !== 'support' || !$isTeam) {
            return false;
        }
        $this->messages->addMember($conversationId, $userId);
        $this->notifications->notify([$userId], 'support', 'You have been added to a private room', 'The admin asked you to help with a student’s private room.', '/studio/support/' . $conversationId);
        $this->notifications->notify([(int) $c['student_id']], 'support', 'A team tutor has joined your private room', null, '/support');

        return true;
    }

    /** @return list<array<string, mixed>> active team tutors who could be added */
    public function teamTutors(): array
    {
        return $this->messages->people('team');
    }

    public function guardianNotified(int $conversationId): void
    {
        $c = $this->messages->conversation($conversationId);
        if ($c !== null && $c['kind'] === 'support') {
            $this->messages->markGuardianNotified($conversationId);
        }
    }

    /** What the tutor sends the guardian: that an invitation was made, never the content. */
    public static function guardianText(string $guardianName, string $studentName): string
    {
        $hello = trim($guardianName) !== '' ? 'Dear ' . trim($guardianName) : 'Hello';

        return $hello . ', this is the SMART Circle tutor. I have invited ' . $studentName
            . ' to a private conversation in SMART Circle about their studies. It stays on record and I will keep you informed. '
            . 'You are welcome to call me with any question.';
    }
}
