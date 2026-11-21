<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Http;

/**
 * Protects every form against forged requests from other sites.
 */
final class Csrf
{
    public const FIELD = '_token';
    private const KEY = '_csrf';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    public function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . e($this->token()) . '">';
    }

    public function isValid(string $submitted): bool
    {
        $token = $this->session->get(self::KEY);

        return is_string($token) && $submitted !== '' && hash_equals($token, $submitted);
    }
}
