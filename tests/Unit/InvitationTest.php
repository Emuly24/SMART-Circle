<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Partners\InvitationService;

final class InvitationTest extends TestCase
{
    public function testTeacherInvitationNeedsSchoolAndSubjects(): void
    {
        $v = InvitationService::validate(['type' => 'teacher_invitation', 'title' => 'Mrs', 'full_name' => 'Grace  Phiri', 'school' => '', 'subjects' => []]);
        self::assertArrayHasKey('school', $v['errors']);
        self::assertArrayHasKey('subjects', $v['errors']);
    }

    public function testSalutationUsesTitleAndSurname(): void
    {
        $v = InvitationService::validate(['type' => 'teacher_invitation', 'title' => 'Mrs', 'full_name' => 'Grace Phiri', 'school' => 'Likuni Girls', 'subjects' => ['biology', 'history']]);
        self::assertSame([], $v['errors']);
        self::assertSame(['Mrs Phiri', 'Mrs Grace Phiri', 'biology'], [$v['clean']['salutation'], $v['clean']['full_name'], $v['clean']['subjects']]);
        $t = InvitationService::validate(['type' => 'tutor_invitation', 'title' => '', 'full_name' => 'Kondwani Banda', 'subjects' => ['physics']]);
        self::assertSame([], $t['errors'], 'a tutor needs no school');
        self::assertSame('Kondwani', $t['clean']['salutation']);
        self::assertArrayHasKey('type', InvitationService::validate(['type' => 'x', 'full_name' => 'Abc Def', 'subjects' => ['physics']])['errors']);
    }
}
