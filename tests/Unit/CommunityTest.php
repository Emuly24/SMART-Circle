<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Community\DiscussionService;
use SmartCircle\Application\Community\ProgressService;

final class CommunityTest extends TestCase
{
    public function testFormatEscapesAndLinksTagsAndMentions(): void
    {
        $h = DiscussionService::format("Why does it float? #density @Chikondi <script>x</script>\nthanks");
        self::assertStringContainsString('<a class="tag-link" href="/community/tags/density">#density</a>', $h);
        self::assertStringContainsString('<strong class="mention">@Chikondi</strong>', $h);
        self::assertStringContainsString('&lt;script&gt;', $h);
        self::assertStringNotContainsString('<script>', $h);
        self::assertStringContainsString('<br>', $h);
        self::assertSame(['density', 'forces'], DiscussionService::hashtags('#Density and #forces, #density again, a#nope'));
    }

    public function testTimesAndNames(): void
    {
        self::assertSame(270000, DiscussionService::parseTime('4:30'));
        self::assertNull(DiscussionService::parseTime('4:75'));
        self::assertNull(DiscussionService::parseTime('soon'));
        self::assertSame('Chikondi (Form 2)', DiscussionService::displayName(['full_name' => 'Chikondi Banda', 'role' => 'student', 'author_form' => 2]));
        self::assertSame('Tutor', DiscussionService::displayName(['full_name' => 'Blessings Emulyn', 'role' => 'tutor']));
        self::assertSame('Teacher Grace', DiscussionService::displayName(['full_name' => 'Grace Phiri', 'role' => 'partner']));
    }

    public function testBadgesAreForEffort(): void
    {
        $earned = ProgressService::earned(['lessons' => 10, 'helped' => 1, 'study_days_7' => 2], ['first_lesson']);
        self::assertSame(['ten_lessons', 'helper'], $earned);
        self::assertSame([], ProgressService::earned([], []));
    }
}
