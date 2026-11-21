<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * A file sent with a form, so the Application layer never touches $_FILES.
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $tmpPath,
        public readonly string $clientName,
        public readonly int $size,
        public readonly int $error,
    ) {
    }

    /** @param array<string, mixed>|null $file one entry of $_FILES */
    public static function fromArray(?array $file): ?self
    {
        if ($file === null || !isset($file['error']) || is_array($file['error'])) {
            return null;
        }
        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return new self((string) ($file['tmp_name'] ?? ''), (string) ($file['name'] ?? ''), (int) ($file['size'] ?? 0), (int) $file['error']);
    }

    public function ok(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_file($this->tmpPath);
    }

    /** Real type from the file's contents, not the name. */
    public function mime(): string
    {
        return $this->ok() ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($this->tmpPath) : '';
    }

    /** Move into place; uses move_uploaded_file for real uploads, rename in tests. */
    public function moveTo(string $target): bool
    {
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        return is_uploaded_file($this->tmpPath) ? move_uploaded_file($this->tmpPath, $target) : rename($this->tmpPath, $target);
    }
}
