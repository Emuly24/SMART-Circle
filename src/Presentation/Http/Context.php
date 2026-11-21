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
