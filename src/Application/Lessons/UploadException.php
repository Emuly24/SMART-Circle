<?php

declare(strict_types=1);

namespace SmartCircle\Application\Lessons;

use RuntimeException;

/** A problem with an upload; `status` suggests the HTTP status to answer with. */
final class UploadException extends RuntimeException
{
    /** @param array<string, mixed> $data extra fields for the client (e.g. received_bytes) */
    public function __construct(string $message, public readonly int $status = 422, public readonly array $data = [])
    {
        parent::__construct($message);
    }
}
