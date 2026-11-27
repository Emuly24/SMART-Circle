<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Reviews\NoteReviewService;

final class NoteReviewTest extends TestCase
{
    public function testSignatureIsRedrawnAsASmallPng(): void
    {
        $img = imagecreatetruecolor(1200, 400);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagejpeg($img);
        $png = NoteReviewService::cleanPng((string) ob_get_clean());
        self::assertNotNull($png);
        $info = getimagesizefromstring($png);
        self::assertSame('image/png', $info['mime']);
        self::assertSame(600, $info[0]);
        self::assertSame(200, $info[1]);
    }

    public function testNonPicturesAreRefused(): void
    {
        self::assertNull(NoteReviewService::cleanPng('not a picture'));
        self::assertNull(NoteReviewService::cleanPng("<?php echo 'x';"));
    }

    public function testTinyPicturesAreRefused(): void
    {
        $img = imagecreatetruecolor(5, 5);
        ob_start();
        imagepng($img);
        self::assertNull(NoteReviewService::cleanPng((string) ob_get_clean()));
    }
}
