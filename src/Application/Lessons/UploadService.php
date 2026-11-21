<?php

declare(strict_types=1);

namespace SmartCircle\Application\Lessons;

use SmartCircle\Data\LessonStorage;
use SmartCircle\Data\Repositories\LessonRepository;
use SmartCircle\Data\Repositories\LessonUploadRepository;

/**
 * Resumable lesson uploads over a weak connection:
 *   start()  -> upload id (or the existing one, so the device continues where it stopped)
 *   chunk()  -> append up to MAX_CHUNK bytes at the expected offset
 *   finish() -> verify size + SHA-256, read the lesson header, create a draft lesson
 */
final class UploadService
{
    public const MAX_CHUNK = 1024 * 1024; // 1 MB per request stays far below hosting POST limits

    public function __construct(
        private readonly LessonUploadRepository $uploads,
        private readonly LessonRepository $lessons,
        private readonly LessonStorage $storage,
        private readonly int $maxLessonBytes,
    ) {
    }

    /** @return array{upload_id: string, received_bytes: int, chunk_size: int, lesson_id: ?int} */
    public function start(int $userId, string $clientKey, string $title, int $size, string $sha256): array
    {
        $clientKey = trim($clientKey);
        $title = trim($title) !== '' ? mb_substr(trim($title), 0, 200) : 'Untitled lesson';
        $sha256 = strtolower($sha256);

        if ($clientKey === '' || strlen($clientKey) > 100) {
            throw new UploadException('Missing lesson key.', 400);
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new UploadException('Missing or invalid checksum.', 400);
        }
        if ($size <= 8) {
            throw new UploadException('The lesson file is empty.', 400);
        }
        if ($size > $this->maxLessonBytes) {
            $mb = (int) round($this->maxLessonBytes / 1048576);
            throw new UploadException("This lesson is too large to upload (limit {$mb} MB). Try splitting it into shorter lessons.", 413);
        }

        $this->cleanUpStale();

        $existing = $this->uploads->findByClient($userId, $clientKey, $sha256);
        if ($existing !== null) {
            return [
                'upload_id' => (string) $existing['id'],
                'received_bytes' => (int) $existing['received_bytes'],
                'chunk_size' => self::MAX_CHUNK,
                'lesson_id' => $existing['lesson_id'] !== null ? (int) $existing['lesson_id'] : null,
            ];
        }

        $id = bin2hex(random_bytes(16));
        $this->uploads->create($id, $userId, $clientKey, $title, $size, $sha256);

        return ['upload_id' => $id, 'received_bytes' => 0, 'chunk_size' => self::MAX_CHUNK, 'lesson_id' => null];
    }

    /** @return array{received_bytes: int} */
    public function chunk(int $userId, string $uploadId, int $offset, string $bytes): array
    {
        $upload = $this->ownedUpload($userId, $uploadId);
        if ($upload['status'] === 'complete') {
            return ['received_bytes' => (int) $upload['size_bytes']];
        }

        $received = (int) $upload['received_bytes'];
        $length = strlen($bytes);

        if ($length === 0 || $length > self::MAX_CHUNK) {
            throw new UploadException('Each piece must be between 1 byte and 1 MB.', 400);
        }
        // A repeated piece (e.g. the reply was lost on a bad connection) is accepted silently.
        if ($offset < $received && $offset + $length <= $received) {
            return ['received_bytes' => $received];
        }
        if ($offset !== $received) {
            throw new UploadException('Piece out of order.', 409, ['received_bytes' => $received]);
        }
        if ($received + $length > (int) $upload['size_bytes']) {
            throw new UploadException('More data than the announced file size.', 400);
        }
        if ($offset === 0 && !str_starts_with($bytes, 'SCL1')) {
            throw new UploadException('This is not a SMART Circle lesson file.', 422);
        }

        $this->storage->append($uploadId, $bytes);
        $received += $length;
        $this->uploads->setReceived($uploadId, $received);

        return ['received_bytes' => $received];
    }

    /** @return array{lesson_id: int} */
    public function finish(int $userId, string $uploadId): array
    {
        $upload = $this->ownedUpload($userId, $uploadId);
        if ($upload['status'] === 'complete' && $upload['lesson_id'] !== null) {
            return ['lesson_id' => (int) $upload['lesson_id']];
        }

        $size = $this->storage->uploadSize($uploadId);
        if ($size !== (int) $upload['size_bytes']) {
            throw new UploadException('The upload is not complete yet.', 409, ['received_bytes' => $size]);
        }
        if (!hash_equals((string) $upload['sha256'], $this->storage->uploadHash($uploadId))) {
            // Corrupted on the way: start again from scratch.
            $this->storage->deleteUpload($uploadId);
            $this->uploads->delete($uploadId);
            throw new UploadException('The file was damaged during upload. Please upload it again.', 422);
        }

        $header = $this->readHeader($uploadId);

        $lessonId = $this->lessons->create([
            'title' => (string) $upload['title'],
            'duration_ms' => max(0, (int) ($header['durationMs'] ?? 0)),
            'size_bytes' => $size,
            'sha256' => (string) $upload['sha256'],
            'parts' => 1,
            'has_voice' => (string) ($header['audioMime'] ?? '') !== '',
            'created_by' => $userId,
        ]);
        $parts = $this->storage->promote($uploadId, $lessonId);
        $this->lessons->updateParts($lessonId, $parts);
        $this->uploads->complete($uploadId, $lessonId);

        return ['lesson_id' => $lessonId];
    }

    /** @return array<string, mixed> */
    private function readHeader(string $uploadId): array
    {
        $prefix = $this->storage->readUploadStart($uploadId, 8);
        if (strlen($prefix) < 8 || !str_starts_with($prefix, 'SCL1')) {
            throw new UploadException('This is not a SMART Circle lesson file.', 422);
        }
        $length = unpack('V', substr($prefix, 4, 4))[1] ?? 0;
        if ($length <= 0 || $length > 50 * 1024 * 1024) {
            throw new UploadException('The lesson file header is damaged.', 422);
        }
        $header = json_decode(substr($this->storage->readUploadStart($uploadId, 8 + $length), 8), true);
        if (!is_array($header) || !isset($header['events']) || !is_array($header['events'])) {
            throw new UploadException('The lesson file header is damaged.', 422);
        }

        return $header;
    }

    /** @return array<string, mixed> */
    private function ownedUpload(int $userId, string $uploadId): array
    {
        $upload = preg_match('/^[a-f0-9]{32}$/', $uploadId) ? $this->uploads->find($uploadId) : null;
        if ($upload === null || (int) $upload['user_id'] !== $userId) {
            throw new UploadException('Upload not found.', 404);
        }

        return $upload;
    }

    private function cleanUpStale(): void
    {
        foreach ($this->uploads->staleIds(7) as $id) {
            $this->storage->deleteUpload($id);
            $this->uploads->delete($id);
        }
    }
}
