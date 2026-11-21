<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmartCircle\Support\PhoneNumber;

final class PhoneNumberTest extends TestCase
{
    /** @return array<string, array{string, ?string}> */
    public static function inputs(): array
    {
        return [
            'local with spaces'       => ['0999 123 456', '+265999123456'],
            'local no spaces'         => ['0888123456', '+265888123456'],
            'nine digits'             => ['999123456', '+265999123456'],
            'international plus'      => ['+265 999 123 456', '+265999123456'],
            'international no plus'   => ['265999123456', '+265999123456'],
            'international 00 prefix' => ['00265999123456', '+265999123456'],
            'dashes and brackets'     => ['(0999)-123-456', '+265999123456'],
            'too short'               => ['099912345', null],
            'too long'                => ['09991234567', null],
            'starts with zero after code' => ['+265 0999 123 45', null],
            'letters'                 => ['phone', null],
            'empty'                   => ['', null],
        ];
    }

    #[DataProvider('inputs')]
    public function testNormalize(string $input, ?string $expected): void
    {
        self::assertSame($expected, PhoneNumber::normalize($input));
    }

    public function testDisplayFormatsTheLocalWay(): void
    {
        self::assertSame('0999 123 456', PhoneNumber::display('+265999123456'));
        self::assertSame('unknown', PhoneNumber::display('unknown'));
    }
}
