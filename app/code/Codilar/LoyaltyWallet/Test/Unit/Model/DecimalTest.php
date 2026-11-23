<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Unit\Model;

use Codilar\LoyaltyWallet\Model\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    #[DataProvider('validProvider')]
    public function testNormalizesValidValues(string|int $input, string $expected): void
    {
        $this->assertTrue(Decimal::isValid($input));
        $this->assertSame($expected, Decimal::normalize($input));
    }

    public static function validProvider(): array
    {
        return [
            'integer' => [5, '5.0000'],
            'integer string' => ['12', '12.0000'],
            'one decimal' => ['12.5', '12.5000'],
            'four decimals' => ['0.0001', '0.0001'],
            'negative' => ['-3.25', '-3.2500'],
            'negative zero' => ['-0', '0.0000'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testRejectsInvalidValues(mixed $input): void
    {
        $this->assertFalse(Decimal::isValid($input));
    }

    public static function invalidProvider(): array
    {
        return [
            'float' => [12.5],
            'five decimals' => ['1.00001'],
            'empty' => [''],
            'text' => ['abc'],
            'leading dot' => ['.5'],
            'exponent' => ['1e3'],
            'null' => [null],
            'too large' => [str_repeat('9', 17)],
        ];
    }

    public function testNormalizeThrowsOnInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::normalize('1.23456');
    }

    public function testArithmeticIsExactWhereFloatsAreNot(): void
    {
        $this->assertSame('0.3000', bcadd('0.1', '0.2', Decimal::SCALE));
        $this->assertSame(0, Decimal::sign(Decimal::normalize('0.0000')));
        $this->assertSame(-1, Decimal::sign('-0.0001'));
    }

    #[DataProvider('divisionProvider')]
    public function testDivideRoundsHalfUp(string $dividend, string $divisor, string $expected): void
    {
        $this->assertSame($expected, Decimal::divideRounded($dividend, $divisor));
    }

    public static function divisionProvider(): array
    {
        return [
            'exact' => ['50', '10', '5.0000'],
            'repeating, rounds down' => ['1', '3', '0.3333'],
            'repeating, rounds up' => ['2', '3', '0.6667'],
            'half rounds up' => ['1', '32768', '0.0000'],
            'fraction rate' => ['3', '0.5', '6.0000'],
        ];
    }

    #[DataProvider('floorProvider')]
    public function testFloorToInt(string $value, int $expected): void
    {
        $this->assertSame($expected, Decimal::floorToInt($value));
    }

    public static function floorProvider(): array
    {
        return [['250.7500', 250], ['0.9999', 0], ['12.0000', 12], ['-3.0000', 0], ['0.0000', 0]];
    }
}
