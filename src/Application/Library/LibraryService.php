<?php

declare(strict_types=1);

namespace SmartCircle\Application\Library;

use SmartCircle\Data\Repositories\LibraryRepository;
use SmartCircle\Support\Subjects;
use SmartCircle\Support\UploadedFile;

/**
 * The free public library. Anyone (no account) can read notes the tutor has chosen to share, as
 * branded printable handouts, and download PDF handouts attached to lessons. Exercises, lessons,
 * Quick 5 and everything else stay for admitted students (with the honesty rule).
 */
final class LibraryService
{
    public const MAX_PDF_BYTES = 9 * 1024 * 1024;

    public function __construct(
        private readonly LibraryRepository $library,
        private readonly string $storageRoot,
    ) {
    }

    /** Reads the browse filters safely. Pure. @return array{form: ?int, subject: ?string} */
    public static function filters(string $form, string $subject): array
    {
        $f = ctype_digit($form) ? (int) $form : 0;

        return ['form' => $f >= 1 && $f <= 4 ? $f : null, 'subject' => isset(Subjects::NAMES[$subject]) ? $subject : null];
    }

    /** @return array{notes: list<array<string, mixed>>, handouts: list<array<string, mixed>>} */
    public function browse(?int $form, ?string $subject): array
    {
        return ['notes' => $this->library->notes($form, $subject), 'handouts' => $this->library->handouts($form, $subject)];
    }

    /** @return array<string, mixed>|null the note with its text and picture blocks */
    public function note(int $id): ?array
    {
        $n = $this->library->note($id);

        return $n === null ? null : $n + ['blocks' => $this->library->blocks($id)];
    }

    /** A picture inside a library note. @return array{path: string, mime: string}|null */
    public function image(int $noteId, int $blockId): ?array
    {
        if ($this->library->note($noteId) === null) {
            return null;
        }
        foreach ($this->library->blocks($noteId) as $b) {
            if ((int) $b['id'] === $blockId && $b['type'] === 'image' && $b['image_path'] !== null) {
                $path = rtrim($this->storageRoot, '/') . '/' . $b['image_path'];
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';

                return is_file($path) ? ['path' => $path, 'mime' => $mime] : null;
            }
        }

        return null;
    }

    /** @return array{path: string, name: string}|null */
    public function handoutFile(int $lessonId): ?array
    {
        $h = $this->library->handout($lessonId);
        if ($h === null) {
            return null;
        }
        $path = rtrim($this->storageRoot, '/') . '/' . $h['handout_path'];
        $name = trim((string) preg_replace('/[^A-Za-z0-9 _-]+/', '', (string) $h['title'])) ?: 'handout';

        return is_file($path) ? ['path' => $path, 'name' => 'SMART Circle - ' . $name . '.pdf'] : null;
    }

    /* ---------- The tutor ---------- */

    public function setNoteInLibrary(int $noteId, bool $on): void
    {
        $this->library->setNoteInLibrary($noteId, $on);
    }

    /** @return string error ('' when saved) */
    public function attachHandout(int $lessonId, ?UploadedFile $file): string
    {
        if ($file === null || !$file->ok()) {
            return 'Choose the PDF handout to upload.';
        }
        if ($file->size > self::MAX_PDF_BYTES) {
            return 'The PDF is larger than 9 MB. Please make it smaller (for example by saving pictures at a lower quality).';
        }
        if ($file->mime() !== 'application/pdf') {
            return 'Upload a PDF file.';
        }
        $relative = 'uploads/handouts/' . $lessonId . '-' . bin2hex(random_bytes(6)) . '.pdf';
        if (!$file->moveTo(rtrim($this->storageRoot, '/') . '/' . $relative)) {
            return 'The handout could not be saved. Please try again.';
        }
        $old = $this->library->lessonHandout($lessonId);
        $this->library->setHandout($lessonId, $relative, $file->size);
        if ($old !== null && $old['handout_path'] !== null) {
            @unlink(rtrim($this->storageRoot, '/') . '/' . $old['handout_path']);
        }

        return '';
    }

    public function removeHandout(int $lessonId): void
    {
        $old = $this->library->lessonHandout($lessonId);
        $this->library->setHandout($lessonId, null, null);
        if ($old !== null && $old['handout_path'] !== null) {
            @unlink(rtrim($this->storageRoot, '/') . '/' . $old['handout_path']);
        }
    }

    /** Path of a lesson's handout whatever its state (for the tutor). */
    public function tutorHandout(int $lessonId): ?string
    {
        $h = $this->library->lessonHandout($lessonId);
        $path = $h !== null && $h['handout_path'] !== null ? rtrim($this->storageRoot, '/') . '/' . $h['handout_path'] : null;

        return $path !== null && is_file($path) ? $path : null;
    }

    public function setHandoutPublic(int $lessonId, bool $on): void
    {
        $this->library->setHandoutPublic($lessonId, $on);
    }
}
