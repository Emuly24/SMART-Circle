<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Http;

/**
 * Per-request state shared by middleware, controllers and templates:
 * who is logged in.
 */
final class Context
{
    /** @var array<string, mixed>|null */
    private ?array $user = null;

    /** @param array<string, mixed>|null $user */
    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return $this->user !== null ? (int) $this->user['id'] : null;
    }

    public function role(): ?string
    {
        return $this->user !== null ? (string) $this->user['role'] : null;
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    private bool $needsOnboarding = false;

    /** @var list<string>|null subjects a team tutor may work in; null = no limit */
    private ?array $subjects = null;

    private ?int $practiceAdmin = null;

    /** @param list<string>|null $subjects */
    public function setSubjects(?array $subjects): void
    {
        $this->subjects = $subjects;
    }

    /** @return list<string>|null null = every subject (the admin) */
    public function subjects(): ?array
    {
        return $this->subjects;
    }

    /** May the signed-in person work in this subject (team tutors: only their own)? */
    public function canTeach(?string $subject): bool
    {
        return $this->subjects === null || ($subject !== null && in_array($subject, $this->subjects, true));
    }

    /** The admin's own id while they are trying the site as a practice account. */
    public function setPracticeAdmin(?int $adminId): void
    {
        $this->practiceAdmin = $adminId;
    }

    public function practiceAdmin(): ?int
    {
        return $this->practiceAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role() === 'tutor';
    }

    public function setNeedsOnboarding(bool $needs): void
    {
        $this->needsOnboarding = $needs;
    }

    /** A newly admitted student who has not yet accepted the class agreement. */
    public function needsOnboarding(): bool
    {
        return $this->needsOnboarding;
    }
}
