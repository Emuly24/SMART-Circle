<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Support\ShareLinks;

final class ShareLinksTest extends TestCase
{
    public function testUsesTheWorkerWhenSet(): void
    {
        self::assertSame('https://share.x.dev/library/notes/6?t=Forces%20%26%20motion', ShareLinks::url('https://share.x.dev/', 'https://smartcircle.gt.tc', '/library/notes/6', 'Forces & motion'));
        self::assertSame('https://smartcircle.gt.tc/about', ShareLinks::url('', 'https://smartcircle.gt.tc/', '/about', 'ignored without a worker'));
    }

    public function testButtons(): void
    {
        $b = ShareLinks::buttons('https://s.dev/', 'Free lessons');
        self::assertSame('https://wa.me/?text=Free%20lessons%20https%3A%2F%2Fs.dev%2F', $b['whatsapp']);
        self::assertStringStartsWith('https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fs.dev%2F', $b['facebook']);
        self::assertArrayHasKey('telegram', $b);
    }

    public function testWorkerIsBuiltFromItsSource(): void
    {
        $dir = dirname(__DIR__, 2) . '/tools/cloudflare';
        $built = (string) file_get_contents($dir . '/share-worker.js');
        self::assertStringContainsString(base64_encode((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/brand/share-card.png')), $built,
            'share-worker.js is out of date: run php tools/cloudflare/build.php');
        self::assertStringNotContainsString('__SHARE_CARD_PNG_BASE64__', $built);
        $lite = (string) file_get_contents($dir . '/share-worker-lite.js');
        self::assertStringContainsString('raw.githubusercontent.com/Emuly24/SMART-Circle/main/public/assets/brand/share-card.png', $lite);
        self::assertLessThan(10000, strlen($lite), 'the small Worker stays easy to paste');
    }
}
