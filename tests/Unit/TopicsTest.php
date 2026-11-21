<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Topics\RequestService;
use SmartCircle\Application\Topics\TopicLineParser;
use SmartCircle\Application\Topics\TopicService;

final class TopicsTest extends TestCase
{
    public function testParsesUnitTitleAndSubtopics(): void
    {
        $t = TopicLineParser::parse('Unit 3: Chemical bonding (Ionic bonds, Covalent bonds, Metallic bonds)');
        self::assertSame('Unit 3', $t['unit']);
        self::assertSame('Chemical bonding', $t['title']);
        self::assertSame(['Ionic bonds', 'Covalent bonds', 'Metallic bonds'], $t['subtopics']);
    }

    public function testKeepsExampleBracketsInTitleAndSkipsBlankLines(): void
    {
        $t = TopicLineParser::parse('Fertilisers (e.g. NPK, urea)');
        self::assertNull($t['unit']);
        self::assertSame([], $t['subtopics']);
        self::assertStringContainsString('e.g.', $t['title']);
        self::assertNull(TopicLineParser::parse('   '));
        self::assertCount(2, TopicLineParser::parseList("Density\n\nPressure (Liquids, Gases)\n"));
    }

    public function testTreeNestsChildrenInOrder(): void
    {
        $rows = [
            ['id' => 3, 'parent_id' => 1, 'sort_order' => 20, 'title' => 'b'],
            ['id' => 1, 'parent_id' => null, 'sort_order' => 20, 'title' => 'Second'],
            ['id' => 2, 'parent_id' => null, 'sort_order' => 10, 'title' => 'First'],
            ['id' => 4, 'parent_id' => 1, 'sort_order' => 10, 'title' => 'a'],
            ['id' => 9, 'parent_id' => 99, 'sort_order' => 10, 'title' => 'orphan'],
        ];
        $tree = TopicService::buildTree($rows);
        self::assertSame(['First', 'Second'], array_column($tree, 'title'));
        self::assertSame(['a', 'b'], array_column($tree[1]['children'], 'title'));
    }

    public function testStudentsBrowseOnlyTheirFormAndTrack(): void
    {
        $humanities = ['form' => 3, 'track' => 'humanities'];
        self::assertTrue(TopicService::mayBrowse($humanities, 3, 'biology'));
        self::assertFalse(TopicService::mayBrowse($humanities, 3, 'physics'));
        self::assertFalse(TopicService::mayBrowse($humanities, 4, 'biology'));
        self::assertTrue(TopicService::mayBrowse(['form' => 1, 'track' => 'general'], 1, 'chemistry'));
        self::assertTrue(TopicService::mayBrowse(null, 4, 'physics'), 'tutor sees everything');
        self::assertFalse(TopicService::mayBrowse(null, 5, 'physics'));
    }

    public function testFirstRequestNeedsDetails(): void
    {
        $v = RequestService::validate(['struggle_type' => 'idea', 'details' => 'short'], true, true);
        self::assertArrayHasKey('details', $v['errors']);

        $v = RequestService::validate(['struggle_type' => 'bogus'], false, true);
        self::assertArrayHasKey('struggle_type', $v['errors']);

        $ok = RequestService::validate(['struggle_type' => 'calculations', 'details' => ''], false, true);
        self::assertSame([], $ok['errors'], 'a "me too" may skip details');
        self::assertFalse($ok['exception']);
    }

    public function testLockedTopicNeedsAReasonAndBecomesEarlyAccess(): void
    {
        $v = RequestService::validate(['struggle_type' => 'idea', 'details' => 'I get lost at the start'], true, false);
        self::assertArrayHasKey('exception_reason', $v['errors']);

        $v = RequestService::validate(['struggle_type' => 'idea', 'details' => 'I get lost at the start',
            'exception_reason' => 'No physics teacher this term'], true, false);
        self::assertSame([], $v['errors']);
        self::assertTrue($v['exception']);
        self::assertSame('No physics teacher this term', $v['reason']);
    }

    public function testRankingPutsTaughtStudentsFirst(): void
    {
        $row = static fn (int $id, int $taught, int $ready, int $eligible, int $votes, string $at = '2026-01-01'): array =>
            ['id' => $id, 'taught_votes' => $taught, 'readiness' => ['ready' => $ready, 'eligible' => $eligible], 'votes' => $votes, 'created_at' => $at];
        $ranked = RequestService::rank([
            $row(1, 0, 1, 6, 5),      // many voices, but nobody's school taught it
            $row(2, 2, 2, 6, 2),
            $row(3, 2, 5, 6, 2),      // same taught voices, more schools have taught it
            $row(4, 1, 6, 6, 1),
        ]);
        self::assertSame([3, 2, 4, 1], array_column($ranked, 'id'));
    }

    public function testBreakdownMostCommonFirstThenCanonicalOrder(): void
    {
        $sorted = RequestService::sortBreakdown(['exam' => 1, 'calculations' => 3, 'idea' => 1]);
        self::assertSame(['calculations', 'idea', 'exam'], array_keys($sorted));
    }
}
