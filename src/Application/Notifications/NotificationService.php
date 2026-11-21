<?php

declare(strict_types=1);

namespace SmartCircle\Application\Notifications;

use SmartCircle\Data\Repositories\NotificationRepository;
use SmartCircle\Data\Repositories\UserRepository;

/** In-app notifications (the bell). No polling: the count is read on normal page loads. */
final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly UserRepository $users,
    ) {
    }

    /** @param list<int> $userIds */
    public function notify(array $userIds, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        if ($userIds === []) {
            return;
        }
        $this->notifications->createMany(
            $userIds,
            $type,
            mb_substr($title, 0, 200),
            $body === null ? null : mb_substr($body, 0, 500),
            $link
        );
    }

    public function notifyTutors(string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        $this->notify($this->users->idsByRole('tutor'), $type, $title, $body, $link);
    }

    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $userId): array
    {
        return $this->notifications->recent($userId);
    }

    public function markAllRead(int $userId): void
    {
        $this->notifications->markAllRead($userId);
    }
}
