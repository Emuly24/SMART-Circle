<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Settings\SiteAssetService;

final class SiteAssetTest extends TestCase
{
    private static function jpeg(int $w, int $h, bool $ink): string
    {
        $i = imagecreatetruecolor($w, $h);
        imagefill($i, 0, 0, (int) imagecolorallocate($i, 250, 250, 250));
        if ($ink) {
            imagefilledrectangle($i, 100, 100, 300, 110, (int) imagecolorallocate($i, 10, 10, 10));
        }
        ob_start();
        imagejpeg($i, null, 95);

        return (string) ob_get_clean();
    }

    public function testSignatureIsCroppedToTheInkOnTransparent(): void
    {
        $png = SiteAssetService::signaturePng(self::jpeg(800, 400, true));
        self::assertNotNull($png);
        $img = imagecreatefromstring($png);
        self::assertLessThan(260, imagesx($img));
        self::assertLessThan(60, imagesy($img));
        self::assertSame(127, (imagecolorat($img, 0, 0) >> 24) & 127, 'paper becomes transparent');
    }

    public function testBlankPaperIsRefused(): void
    {
        self::assertNull(SiteAssetService::signaturePng(self::jpeg(400, 200, false)));
        self::assertNull(SiteAssetService::signaturePng('nope'));
    }

    public function testPhotoIsResizedAsJpeg(): void
    {
        $jpg = SiteAssetService::photoJpeg(self::jpeg(2000, 1500, false));
        $info = getimagesizefromstring((string) $jpg);
        self::assertSame([900, 675, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);
        self::assertNull(SiteAssetService::photoJpeg('nope'));
    }
}
