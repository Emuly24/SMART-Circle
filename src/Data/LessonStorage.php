<?php

declare(strict_types=1);

namespace SmartCircle\Data;

use RuntimeException;

/**
 * Lesson files on disk (storage/, never web-reachable).
 *
 * InfinityFree limits each file to 10 MB, so a lesson is stored as numbered parts
 * of at most PART_LIMIT bytes: part-000.bin, part-001.bin, ... Concatenated in
 * order they form the original .scl file.
 *
 *   storage/uploads/lessons/{uploadId}/   while an upload is in progress
 *   storage/lessons/{lessonId}/           once the upload is complete
 */
final class LessonStorage
{
    public const PART_LIMIT = 9 * 1024 * 1024;

    private readonly string $storageRoot;

    /** @param int $partLimit bytes per part file (smaller only in tests) */
    public function __construct(string $storageRoot, private readonly int $partLimit = self::PART_LIMIT)
    {
        $this->storageRoot = rtrim($storageRoot, '/');
    }

    /** Append bytes to an upload, starting a new part whenever the current one is full. */
    public function append(string $uploadId, string $bytes): void
    {
        $dir = $this->uploadDir($uploadId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create upload folder');
        }

        while ($bytes !== '') {
            $parts = $this->partFiles($dir);
            $current = $parts === [] ? $this->partPath($dir, 0) : end($parts);
            $size = is_file($current) ? (int) filesize($current) : 0;
            if ($size >= $this->partLimit) {
                $current = $this->partPath($dir, count($parts));
                $size = 0;
            }
            $room = $this->partLimit - $size;
            $piece = substr($bytes, 0, $room);
            if (file_put_contents($current, $piece, FILE_APPEND | LOCK_EX) !== strlen($piece)) {
                throw new RuntimeException('Could not write lesson data (disk full?)');
            }
            $bytes = (string) substr($bytes, strlen($piece));
            clearstatcache(true, $current);
        }
    }

    public function uploadSize(string $uploadId): int
    {
        return array_sum(array_map('filesize', $this->partFiles($this->uploadDir($uploadId))));
    }

    /** SHA-256 of the whole upload, streamed part by part (no 10 MB+ strings in memory). */
    public function uploadHash(string $uploadId): string
    {
        return $this->hashParts($this->partFiles($this->uploadDir($uploadId)));
    }

    /** First bytes of the upload (the lesson header lives there). */
    public function readUploadStart(string $uploadId, int $length): string
    {
        $parts = $this->partFiles($this->uploadDir($uploadId));
        $out = '';
        foreach ($parts as $part) {
            $out .= (string) file_get_contents($part, false, null, 0, $length - strlen($out));
            if (strlen($out) >= $length) {
                break;
            }
        }

        return $out;
    }

    /** Move a finished upload into its permanent lesson folder. Returns the number of parts. */
    public function promote(string $uploadId, int $lessonId): int
    {
        $from = $this->uploadDir($uploadId);
        $to = $this->lessonDir($lessonId);
        if (!is_dir(dirname($to))) {
            mkdir(dirname($to), 0755, true);
        }
        if (is_dir($to)) {
            $this->deleteDir($to);
        }
        if (!rename($from, $to)) {
            throw new RuntimeException('Could not move the lesson into place');
        }

        return count($this->partFiles($to));
    }

    /** @return list<string> absolute part paths of a stored lesson, in order */
    public function lessonParts(int $lessonId): array
    {
        return $this->partFiles($this->lessonDir($lessonId));
    }

    public function deleteLesson(int $lessonId): void
    {
        $this->deleteDir($this->lessonDir($lessonId));
    }

    public function deleteUpload(string $uploadId): void
    {
        $this->deleteDir($this->uploadDir($uploadId));
    }

    /** @param list<string> $parts */
    public function hashParts(array $parts): string
    {
        $ctx = hash_init('sha256');
        foreach ($parts as $part) {
            $fh = fopen($part, 'rb');
            if ($fh === false) {
                throw new RuntimeException('Cannot read lesson part');
            }
            hash_update_stream($ctx, $fh);
            fclose($fh);
        }

        return hash_final($ctx);
    }

    private function uploadDir(string $uploadId): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
            throw new RuntimeException('Invalid upload id');
        }

        return $this->storageRoot . '/uploads/lessons/' . $uploadId;
    }

    private function lessonDir(int $lessonId): string
    {
        return $this->storageRoot . '/lessons/' . $lessonId;
    }

    private function partPath(string $dir, int $index): string
    {
        return sprintf('%s/part-%03d.bin', $dir, $index);
    }

    /** @return list<string> */
    private function partFiles(string $dir): array
    {
        $files = glob($dir . '/part-*.bin') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
