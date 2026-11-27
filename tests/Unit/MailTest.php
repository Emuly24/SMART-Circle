<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Admissions\DecisionEmail;
use SmartCircle\Application\Mail\MailService;
use SmartCircle\Data\Mail\SmtpTransport;

final class MailTest extends TestCase
{
    public function testMessageHasBothPartsAndEncodesUnicode(): void
    {
        $m = SmtpTransport::message('smartcircle@zohomail.com', 'SMART Circle', 'mum@example.com', 'Mayi Chisomo', 'Takulandirani – Chikondi', "Hello\n.dot line", '<p>Hi</p>', 'BOUND', 0);
        self::assertStringContainsString('From: SMART Circle <smartcircle@zohomail.com>', $m);
        self::assertStringContainsString('Subject: =?UTF-8?B?' . base64_encode('Takulandirani – Chikondi') . '?=', $m);
        self::assertStringContainsString('Content-Type: multipart/alternative; boundary="BOUND"', $m);
        self::assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $m);
        self::assertStringContainsString('Content-Type: text/html; charset=UTF-8', $m);
        self::assertStringContainsString(chunk_split(base64_encode("Hello\n.dot line"), 76, "\r\n"), $m . "\r\n");
        self::assertStringEndsWith('--BOUND--', $m);
        self::assertDoesNotMatchRegularExpression('/^\.[^.]/m', $m, 'lines starting with a dot are doubled');
    }

    public function testHtmlEscapesAndLinks(): void
    {
        $h = MailService::html('Hi <b>', "Hello <script>x</script>\n\nOpen https://smartcircle.gt.tc/notes/3.");
        self::assertStringContainsString('Hi &lt;b&gt;', $h);
        self::assertStringContainsString('&lt;script&gt;', $h);
        self::assertStringNotContainsString('<script>', $h);
        self::assertStringContainsString('<a href="https://smartcircle.gt.tc/notes/3"', $h);
    }

    public function testDecisionEmails(): void
    {
        $app = ['full_name' => 'Chikondi Banda', 'form' => 2, 'reference' => 'SC-27-ABCDE'];
        $a = DecisionEmail::build($app, 'admitted', 'https://smartcircle.gt.tc', 'LQ4PQG87');
        self::assertSame('Welcome to SMART Circle, Chikondi!', $a['subject']);
        self::assertStringContainsString('https://smartcircle.gt.tc/letters/LQ4PQG87', $a['text']);
        self::assertStringNotContainsString('setup code:', strtolower($a['text']), 'the setup code is never emailed');
        self::assertStringContainsString('/library', DecisionEmail::build($app, 'declined', 'https://x.test', null)['text']);
        self::assertNull(DecisionEmail::build($app, 'shortlisted', 'https://x.test', null));
    }
}
