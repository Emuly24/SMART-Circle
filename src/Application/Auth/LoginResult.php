<?php

declare(strict_types=1);

namespace SmartCircle\Application\Auth;

final class LoginResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $error,
        public readonly ?int $userId,
        public readonly ?string $role,
        public readonly bool $mustChangePassword,
    ) {
    }

    public static function failed(string $error): self
    {
        return new self(false, $error, null, null, false);
    }

    public static function succeeded(int $userId, string $role, bool $mustChangePassword): self
    {
        return new self(true, '', $userId, $role, $mustChangePassword);
    }
}
