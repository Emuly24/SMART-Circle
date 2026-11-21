<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Http;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public const FORM_EXPIRED = 'Your form expired. Please go back, refresh the page and try again.';

    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            403 => 'You do not have access to this page.',
            404 => 'We could not find that page.',
            405 => 'That action is not allowed here.',
            default => 'Something went wrong. Please try again in a moment.',
        };
    }
}
