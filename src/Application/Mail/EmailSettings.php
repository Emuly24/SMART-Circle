<?php

declare(strict_types=1);

namespace SmartCircle\Application\Mail;

use SmartCircle\Data\Repositories\UserRepository;

/** A member's email address and whether they also want their notifications by email. */
final class EmailSettings
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /** @return string error ('' when saved) */
    public function save(int $userId, string $email, bool $notifications): string
    {
        $email = trim($email);
        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            return 'That email address does not look right.';
        }
        $this->users->setEmailSettings($userId, $email === '' ? null : strtolower($email), $notifications);

        return '';
    }
}
