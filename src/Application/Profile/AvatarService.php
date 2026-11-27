<?php

declare(strict_types=1);

namespace SmartCircle\Application\Profile;

use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Support\UploadedFile;

/**
 * Profile pictures: made square and small (256 px JPEG, usually under 40 KB) so they suit the
 * hosting limits and save students' data. Stored outside the web root and served to members only.
 */
final class AvatarService
{
    public const SIZE = 256;
    public const MAX_UPLOAD = 6 * 1024 * 1024;
    private const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly UserRepository $users,
        private readonly string $storageRoot,
    ) {
    }

    public function path(int $userId): string
    {
        return rtrim($this->storageRoot, '/') . '/avatars/' . $userId . '.jpg';
    }

    /** @return string|null error */
    public function save(int $userId, ?UploadedFile $file): ?string
    {
        if ($file === null || !$file->ok()) {
            return 'Choose a photo first.';
        }
        if ($file->size > self::MAX_UPLOAD) {
            return 'That photo is too large. Please choose a smaller one.';
        }
        if (!in_array($file->mime(), self::TYPES, true)) {
            return 'Use a JPG, PNG or WebP photo.';
        }
        $target = $this->path($userId);
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return 'The photo could not be saved. Please try again later.';
        }
        if (function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring((string) file_get_contents($file->tmpPath));
            if ($src === false) {
                return 'That file does not look like a photo.';
            }
            [$w, $h] = [imagesx($src), imagesy($src)];
            $side = min($w, $h);
            $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
            imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);
            $ok = imagejpeg($dst, $target, 82);
            imagedestroy($src);
            imagedestroy($dst);
            if (!$ok) {
                return 'The photo could not be saved. Please try again later.';
            }
        } elseif ($file->mime() !== 'image/jpeg' || $file->size > 300 * 1024 || !$file->moveTo($target)) {
            return 'Please use a small JPG photo (under 300 KB).';
        }
        $this->users->setAvatar($userId, true);

        return null;
    }

    public function remove(int $userId): void
    {
        $p = $this->path($userId);
        if (is_file($p)) {
            unlink($p);
        }
        $this->users->setAvatar($userId, false);
    }
}
