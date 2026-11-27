<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Partners\ContributionService;

final class ContributionsTest extends TestCase
{
    public function testResourceValidation(): void
    {
        $ok = ContributionService::validateResource(['kind' => 'scheme_of_work', 'title' => ' Form 3 Chemistry scheme ', 'subject' => 'chemistry', 'form' => '3', 'note' => '']);
        self::assertSame([], $ok['errors']);
        self::assertSame(['kind' => 'scheme_of_work', 'title' => 'Form 3 Chemistry scheme', 'subject' => 'chemistry', 'form' => 3, 'note' => null], $ok['clean']);
        $any = ContributionService::validateResource(['kind' => 'notes', 'title' => 'Handout', 'subject' => '', 'form' => '']);
        self::assertNull($any['clean']['subject']);
        self::assertNull($any['clean']['form']);
        $bad = ContributionService::validateResource(['kind' => 'video', 'title' => 'x', 'subject' => 'history', 'form' => '7']);
        self::assertSame(['kind', 'title', 'subject', 'form'], array_keys($bad['errors']));
    }

    public function testWhatsappMessage(): void
    {
        $t = ContributionService::whatsappText('Mr Banda', 'Ntcheu Secondary', ['kind' => 'past_paper', 'title' => 'Mock 2026', 'subject' => 'physics', 'form' => 4]);
        self::assertSame('Hello, this is Mr Banda from Ntcheu Secondary (SMART Circle partner). I am sending you: Mock 2026 (Past paper or test, Form 4 Physics).', $t);
        self::assertStringEndsWith('(Syllabus).', ContributionService::whatsappText('A', 'B', ['kind' => 'syllabus', 'title' => 'X', 'subject' => null, 'form' => null]));
    }

    public function testFileLimitsSuitTheHosting(): void
    {
        self::assertLessThan(10 * 1024 * 1024, ContributionService::MAX_BYTES);
        self::assertArrayHasKey('application/pdf', ContributionService::TYPES);
        self::assertArrayNotHasKey('application/x-msdownload', ContributionService::TYPES);
    }
}
