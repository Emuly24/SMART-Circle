<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * Structured JSON responses for future AJAX / Fetch API clients.
 */
final class JsonResponse
{
    public static function send(bool $success, string $message = '', array $data = [], array $errors = [], int $statusCode = 200): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        exit;
    }

    public static function success(string $message = '', array $data = [], int $statusCode = 200): void
    {
        self::send(true, $message, $data, [], $statusCode);
    }

    public static function error(string $message, array $errors = [], int $statusCode = 400): void
    {
        self::send(false, $message, [], $errors, $statusCode);
    }
}
