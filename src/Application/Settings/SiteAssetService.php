<?php

declare(strict_types=1);

namespace SmartCircle\Application\Settings;

use SmartCircle\Data\Repositories\SettingsRepository;
use SmartCircle\Support\UploadedFile;

/**
 * The tutor's signature (printed on letters and certificates) and photo (on the About page).
 * Uploaded once by the admin and kept in storage/, never in the code repository.
 */
final class SiteAssetService
{
    public const KINDS = ['signature' => 'tutor_signature', 'photo' => 'about_photo'];
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly string $storageRoot,
    ) {
    }

    /** @return array{signature: ?string, photo: ?string} a cache version for each asset that exists */
    public function versions(): array
    {
        $out = ['signature' => null, 'photo' => null];
        foreach (self::KINDS as $kind => $key) {
            $rel = $this->settings->get($key);
            if ($rel !== null && $rel !== '' && is_file($this->path($rel))) {
                $out[$kind] = substr(md5($rel), 0, 8);
            }
        }

        return $out;
    }

    /** @return array{path: string, mime: string}|null */
    public function file(string $kind): ?array
    {
        $rel = isset(self::KINDS[$kind]) ? $this->settings->get(self::KINDS[$kind]) : null;
        if ($rel === null || $rel === '' || !is_file($this->path($rel))) {
            return null;
        }

        return ['path' => $this->path($rel), 'mime' => $kind === 'signature' ? 'image/png' : 'image/jpeg'];
    }

    /** @return string error ('' when saved) */
    public function upload(string $kind, ?UploadedFile $file): string
    {
        if (!isset(self::KINDS[$kind])) {
            return 'Unknown picture.';
        }
        if ($file === null || !$file->ok()) {
            return 'Choose a picture to upload.';
        }
        if ($file->size > self::MAX_BYTES || !in_array($file->mime(), ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return 'Use a JPG, PNG or WebP picture smaller than 8 MB.';
        }
        $bytes = (string) file_get_contents($file->tmpPath);
        $data = $kind === 'signature' ? self::signaturePng($bytes) : self::photoJpeg($bytes);
        if ($data === null) {
            return 'That picture could not be read. Please try another.';
        }
        $rel = 'uploads/site/' . $kind . '-' . bin2hex(random_bytes(6)) . ($kind === 'signature' ? '.png' : '.jpg');
        $path = $this->path($rel);
        if ((!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) || file_put_contents($path, $data) === false) {
            return 'The picture could not be saved. Please try again.';
        }
        $old = $this->settings->get(self::KINDS[$kind]);
        $this->settings->set(self::KINDS[$kind], $rel);
        if ($old !== null && $old !== '') {
            @unlink($this->path($old));
        }

        return '';
    }

    public function remove(string $kind): void
    {
        if (!isset(self::KINDS[$kind])) {
            return;
        }
        $old = $this->settings->get(self::KINDS[$kind]);
        $this->settings->set(self::KINDS[$kind], '');
        if ($old !== null && $old !== '') {
            @unlink($this->path($old));
        }
    }

    /**
     * A signature photographed on white paper becomes dark ink on a transparent background, cropped to the ink.
     * Pure apart from GD.
     */
    public static function signaturePng(string $bytes): ?string
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] * $info[1] > 40_000_000) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        // Work on a copy at most 1200 px wide.
        $scale = min(1, 1200 / imagesx($src));
        [$w, $h] = [max(1, (int) round(imagesx($src) * $scale)), max(1, (int) round(imagesy($src) * $scale))];
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 255, 255, 255));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        $lum = static function (int $c): float {
            return ((($c >> 16) & 255) + (($c >> 8) & 255) + ($c & 255)) / 3;
        };
        [$minX, $minY, $maxX, $maxY] = [$w, $h, -1, -1];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($lum(imagecolorat($img, $x, $y)) < 150) {
                    [$minX, $minY, $maxX, $maxY] = [min($minX, $x), min($minY, $y), max($maxX, $x), max($maxY, $y)];
                }
            }
        }
        if ($maxX < 0) {
            return null;
        }
        $pad = 12;
        [$minX, $minY] = [max(0, $minX - $pad), max(0, $minY - $pad)];
        [$maxX, $maxY] = [min($w - 1, $maxX + $pad), min($h - 1, $maxY + $pad)];
        [$cw, $ch] = [$maxX - $minX + 1, $maxY - $minY + 1];
        $out = imagecreatetruecolor($cw, $ch);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        for ($y = 0; $y < $ch; $y++) {
            for ($x = 0; $x < $cw; $x++) {
                $l = $lum(imagecolorat($img, $minX + $x, $minY + $y));
                $alpha = $l >= 215 ? 127 : ($l <= 90 ? 0 : (int) round(127 * ($l - 90) / 125));
                imagesetpixel($out, $x, $y, (int) imagecolorallocatealpha($out, 20, 33, 61, $alpha));
            }
        }
        ob_start();
        imagepng($out, null, 9);

        return (string) ob_get_clean();
    }

    /** A photo redrawn as a JPEG at most 900 px wide (drops location and camera details). Pure apart from GD. */
    public static function photoJpeg(string $bytes): ?string
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 100 || $info[1] < 100 || $info[0] * $info[1] > 40_000_000) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        $scale = min(1, 900 / imagesx($src));
        [$w, $h] = [max(1, (int) round(imagesx($src) * $scale)), max(1, (int) round(imagesy($src) * $scale))];
        $dst = imagecreatetruecolor($w, $h);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        ob_start();
        imagejpeg($dst, null, 82);

        return (string) ob_get_clean();
    }

    private function path(string $rel): string
    {
        return rtrim($this->storageRoot, '/') . '/' . $rel;
    }
}
