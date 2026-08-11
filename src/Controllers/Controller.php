<?php

declare(strict_types=1);

namespace SmartCircle\Controllers;

use SmartCircle\Support\Csrf;
use SmartCircle\Support\ErrorLogger;
use SmartCircle\Support\JsonResponse;
use Throwable;

/**
 * Base controller — renders views, handles redirects, CSRF, and JSON responses.
 */
abstract class Controller
{
    protected function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewPath = dirname(__DIR__, 2) . '/views/' . $view . '.php';

        if (!is_file($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        require $viewPath;
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $format = $_GET['format'] ?? $_POST['format'] ?? '';

        return str_contains($accept, 'application/json')
            || strcasecmp($requestedWith, 'XMLHttpRequest') === 0
            || $format === 'json';
    }

    protected function csrfToken(): string
    {
        return Csrf::signedToken();
    }

    protected function csrfField(): string
    {
        return Csrf::hiddenField();
    }

    protected function validateCsrf(): bool
    {
        $token = $_POST[Csrf::FIELD_NAME] ?? null;

        return Csrf::validate(is_string($token) ? $token : null);
    }

    /** @param array<string, string> $errors */
    protected function jsonSuccess(string $message = '', array $data = [], int $statusCode = 200): void
    {
        JsonResponse::success($message, $data, $statusCode);
    }

    /** @param array<string, string> $errors */
    protected function jsonError(string $message, array $errors = [], int $statusCode = 400): void
    {
        JsonResponse::error($message, $errors, $statusCode);
    }

    protected function respondValidationFailure(string $message, array $errors = []): void
    {
        if ($this->wantsJson()) {
            $this->jsonError($message, $errors, 422);
        }
    }

    protected function respondCsrfFailure(): void
    {
        if ($this->wantsJson()) {
            $this->jsonError('Invalid or expired security token. Please refresh and try again.', [], 403);
        }

        http_response_code(403);
        $this->render('errors/generic', [
            'pageTitle' => 'Request Blocked - SMART Circle',
            'message' => 'Your session expired or the form was invalid. Please go back and try again.',
        ]);
        exit;
    }

    protected function runSafely(callable $action): void
    {
        try {
            $action();
        } catch (Throwable $exception) {
            ErrorLogger::log($exception, [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            ]);

            if ($this->wantsJson()) {
                $this->jsonError('An unexpected error occurred. Please try again later.', [], 500);
            }

            http_response_code(500);
            $this->render('errors/generic', [
                'pageTitle' => 'Error - SMART Circle',
                'message' => 'Something went wrong. Please try again later.',
            ]);
            exit;
        }
    }
}
