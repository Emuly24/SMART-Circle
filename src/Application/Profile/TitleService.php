<?php

declare(strict_types=1);

namespace SmartCircle\Application\Profile;

use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Support\NameTags;

/** How a teacher or tutor is addressed (Mr, Mrs, Dr ...), used by name tags in messages and emails. */
final class TitleService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /** @return string|null an error, or null when saved */
    public function save(int $userId, string $title): ?string
    {
        $title = trim($title);
        if ($title !== '' && !in_array($title, NameTags::TITLES, true)) {
            return 'Choose a title from the list.';
        }
        $this->users->setTitle($userId, $title === '' ? null : $title);

        return null;
    }

    /** The tutor sets the title of a partner teacher or team tutor. @return string|null an error */
    public function saveFor(int $userId, string $title): ?string
    {
        $u = $this->users->findById($userId);
        if ($u === null || !in_array($u['role'], ['partner', 'team', 'tutor', 'alumni'], true)) {
            return 'Titles are for teachers and tutors.';
        }

        return $this->save($userId, $title);
    }
}
