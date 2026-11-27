<?php

declare(strict_types=1);

namespace SmartCircle\Application\Partners;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\GuestRepository;

/**
 * Guest Teacher: the tutor lets a partner teacher record lessons on the board, in the subjects they teach.
 * Their lessons stay drafts that only they (and the tutor) see; "publish" asks the tutor, who publishes.
 * Students see "Guest teacher: name, school" on the lesson.
 */
final class GuestTeacherService
{
    public function __construct(
        private readonly GuestRepository $guests,
        private readonly NotificationService $notifications,
    ) {
    }

    /** @return list<string>|null the subjects a guest teacher may record in; null when not a guest teacher */
    public function scope(int $userId): ?array
    {
        return $this->guests->guest($userId)['subjects'] ?? null;
    }

    public function setGuest(int $partnerId, bool $on): void
    {
        $this->guests->setGuest($partnerId, $on);
        if ($on) {
            $this->notifications->notify([$partnerId], 'guest', 'You can now record lessons as a guest teacher',
                'Open "Record a lesson" from your dashboard. When a lesson is ready, send it to the tutor, who publishes it with your name on it.', '/dashboard');
        }
    }

    public function submit(int $lessonId, string $guestName, string $title): void
    {
        $this->guests->submit($lessonId);
        $this->notifications->notifyTutors('guest', 'Guest lesson ready to publish: ' . $title, 'Recorded by ' . $guestName . '.', '/studio/lessons/' . $lessonId);
    }

    /** After the tutor publishes a lesson: thank the guest teacher who recorded it. */
    public function published(int $lessonId, string $title): void
    {
        $guest = $this->guests->creatorOf($lessonId);
        if ($guest !== null) {
            $this->notifications->notify([$guest], 'guest', 'Your lesson is published: ' . $title, 'Students now see it with your name. Thank you for teaching!', '/studio/lessons/' . $lessonId);
        }
    }

    /** @return array{name: string, school: ?string}|null */
    public function credit(int $lessonId): ?array
    {
        return $this->guests->credit($lessonId);
    }

    /** @return array<int, array{name: string, submitted_at: ?string}> */
    public function guestLessons(): array
    {
        return $this->guests->guestLessons();
    }
}
