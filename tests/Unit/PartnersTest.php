<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Partners\PartnerService;
use SmartCircle\Application\Partners\SchoolService;

final class PartnersTest extends TestCase
{
    public function testValidInviteIsCleaned(): void
    {
        $v = PartnerService::validate([
            'full_name' => '  Grace   Banda ', 'phone' => '0991 234 567', 'email' => '',
            'school_name' => 'Likuni  Girls Secondary', 'district' => 'Lilongwe', 'subjects' => ['physics', 'bogus', 'mathematics'],
        ]);
        self::assertSame([], $v['errors']);
        self::assertSame('Grace Banda', $v['clean']['full_name']);
        self::assertSame('+265991234567', $v['clean']['phone']);
        self::assertNull($v['clean']['email']);
        self::assertSame('Likuni Girls Secondary', $v['clean']['school_name']);
        self::assertSame(['mathematics', 'physics'], $v['clean']['subjects'], 'canonical order, unknown dropped');
    }

    public function testInviteNeedsNamePhoneSchoolDistrictAndSubject(): void
    {
        $v = PartnerService::validate(['full_name' => 'Grace', 'phone' => '123', 'email' => 'nope', 'school_name' => '', 'district' => 'Atlantis']);
        self::assertSame(['full_name', 'phone', 'email', 'school_name', 'district', 'subjects'], array_keys($v['errors']));
    }

    public function testCoverageKeepsOnlyAllowedTopicsAndKnownStatuses(): void
    {
        $clean = SchoolService::cleanCoverage(
            ['1' => 'covered', '2' => '', '3' => 'hacked', '4' => 'next', '99' => 'covered', '5' => ['x']],
            [1 => true, 2 => true, 3 => true, 4 => true, 5 => true]
        );
        self::assertSame([1 => 'covered', 2 => '', 4 => 'next'], $clean);
    }
}
