<?php

declare(strict_types=1);

namespace SmartCircle\Support;

use Throwable;

/**
 * Writes application errors to a private log file — never echoed to the user.
 */
final class ErrorLogger
{
    private static ?string $logDir = null;

    public static function log(Throwable $exception, array $context = []): void
    {
        $dir = self::logDirectory();

        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            error_log('ErrorLogger: unable to create log directory: ' . $dir);
            error_log($exception->getMessage());

            return;
        }

        $file = $dir . '/app-' . date('Y-m-d') . '.log';
        $entry = sprintf(
            "[%s] %s in %s:%d\nContext: %s\nTrace:\n%s\n\n",
            date('c'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}',
            $exception->getTraceAsString()
        );

        file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }

    private static function logDirectory(): string
    {
        if (self::$logDir !== null) {
            return self::$logDir;
        }

        self::$logDir = dirname(__DIR__, 2) . '/storage/logs';

        return self::$logDir;
    }
}
