<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Guardians\GuardianMessages;
use SmartCircle\Application\Guardians\GuardianService;

final class GuardiansTest extends TestCase
{
    private const D = ['guardian' => 'Mayi Tembo', 'child' => 'Tiwonge Tembo', 'child_first' => 'Tiwonge', 'form' => '2', 'school' => 'Ntcheu Secondary',
        'link' => 'https://smartcircle.gt.tc/g/abc', 'tutor' => 'Blessings', 'tutor_phone' => '0881 193 707'];

    public function testAdmissionMessageInBothLanguages(): void
    {
        $en = GuardianMessages::admission('en', self::D);
        foreach (['Hello Mayi Tembo', 'Tiwonge Tembo (Form 2, Ntcheu Secondary)', 'completely free', 'phone or tablet', 'data', 'quiet time', 'https://smartcircle.gt.tc/g/abc', '0881 193 707'] as $s) {
            self::assertStringContainsString($s, $en);
        }
        $ny = GuardianMessages::admission('ny', self::D);
        foreach (['Moni Mayi Tembo', 'Fomu 2', 'zaulere', 'foni kapena tabuleti', 'https://smartcircle.gt.tc/g/abc', 'Zikomo'] as $s) {
            self::assertStringContainsString($s, $ny);
        }
        self::assertStringNotContainsString('call me', GuardianMessages::admission('en', ['tutor_phone' => ''] + self::D));
    }

    public function testMonthlyUpdateEncouragesKindly(): void
    {
        self::assertStringContainsString('a little encouragement', GuardianMessages::monthly('en', self::D, 0, 0, 3));
        self::assertStringContainsString('proud of them', GuardianMessages::monthly('en', self::D, 6, 3, 3));
        $m = GuardianMessages::monthly('en', self::D, 1, 1, 3);
        self::assertStringContainsString('for March: 1 lesson watched and 1 piece of work', $m);
        self::assertStringContainsString('mwezi wa Novembala', GuardianMessages::monthly('ny', self::D, 2, 1, 11));
        self::assertStringContainsString('tikunyadira', GuardianMessages::monthly('ny', self::D, 9, 0, 1));
    }

    public function testCallScriptAndConfirmPage(): void
    {
        self::assertCount(6, GuardianMessages::callScript('ny', 'Tiwonge'));
        self::assertStringContainsString('no payment or favour', implode(' ', GuardianMessages::callScript('en', 'Tiwonge')));
        $p = GuardianMessages::confirmPage('ny', 'Tiwonge');
        self::assertStringContainsString('Tiwonge', $p['yes']);
        self::assertSame('Read in English', $p['switch']);
    }

    public function testLinksPreferTheWhatsappNumber(): void
    {
        $g = ['guardian_phone' => '+265887000111', 'guardian_whatsapp' => '+265997000111', 'guardian_email' => 'a@b.mw'];
        $l = GuardianService::links($g, 'Hi there', 'Monthly', 'Subject');
        self::assertSame('https://wa.me/265997000111?text=Hi%20there', $l['whatsapp']);
        self::assertSame('tel:+265887000111', $l['tel']);
        self::assertStringStartsWith('mailto:a@b.mw?subject=Subject&body=Hi%20there', (string) $l['email']);
        $none = GuardianService::links(['guardian_phone' => '', 'guardian_whatsapp' => null, 'guardian_email' => 'bad'], 'x', 'y', 'z');
        self::assertNull($none['whatsapp']);
        self::assertNull($none['email']);
        self::assertSame('https://wa.me/265887000111?text=x', GuardianService::links(['guardian_phone' => '0887000111', 'guardian_whatsapp' => null], 'x', 'y', 'z')['whatsapp']);
    }

    public function testStatus(): void
    {
        self::assertSame('waiting', GuardianService::status(['guardian_declined_at' => null, 'guardian_confirmed_at' => null]));
        self::assertSame('confirmed', GuardianService::status(['guardian_declined_at' => null, 'guardian_confirmed_at' => '2026-12-01']));
        self::assertSame('declined', GuardianService::status(['guardian_declined_at' => '2026-12-01', 'guardian_confirmed_at' => null]));
    }
}
