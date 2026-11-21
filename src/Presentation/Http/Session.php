<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Http;

use SessionHandlerInterface;

/**
 * The only place a PHP session is started. Wraps $_SESSION so the rest of the
 * code never touches it directly.
 */
final class Session
{
    private const USER_KEY = 'auth_user_id';
    private const LAST_SEEN_KEY = '_last_seen';
    private const FLASH_KEY = '_flash';

    public function start(SessionHandlerInterface $handler, string $name, bool $secure, int $idleTimeout): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $idleTimeout);
        session_set_save_handler($handler, true);
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Idle timeout: forget the login after too long without activity.
        $now = time();
        $lastSeen = (int) ($_SESSION[self::LAST_SEEN_KEY] ?? $now);
        if ($now - $lastSeen > $idleTimeout) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION[self::LAST_SEEN_KEY] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function userId(): ?int
    {
        $id = $_SESSION[self::USER_KEY] ?? null;

        return is_int($id) ? $id : null;
    }

    /** New session id on login prevents session fixation. */
    public function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION[self::USER_KEY] = $userId;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?: 'Lax',
            ]);
        }
        session_destroy();
    }

    /** One-time message shown on the next page. */
    public function flash(string $type, string $message): void
    {
        $_SESSION[self::FLASH_KEY][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public function takeFlashes(): array
    {
        $flashes = $_SESSION[self::FLASH_KEY] ?? [];
        unset($_SESSION[self::FLASH_KEY]);

        return is_array($flashes) ? array_values($flashes) : [];
    }
}
