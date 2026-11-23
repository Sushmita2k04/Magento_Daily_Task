<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Unit\Model\Points;

use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Points\AccrualCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AccrualCalculatorTest extends TestCase
{
    private function calculator(string $rate): AccrualCalculator
    {
        $config = $this->createStub(Config::class);
        $config->method('getAccrualRate')->willReturn($rate);
        return new AccrualCalculator($config);
    }

    #[DataProvider('cases')]
    public function testCalculatesWholePointsRoundedDown(string $amount, string $rate, int $expected): void
    {
        $this->assertSame($expected, $this->calculator($rate)->calculate($amount));
    }

    public static function cases(): array
    {
        return [
            'one point per rupee' => ['250.75', '1.0000', 250],
            'fraction is dropped, never rounded up' => ['99.9999', '1.0000', 99],
            'higher rate' => ['100', '2.5000', 250],
            'fractional rate' => ['10', '0.5000', 5],
            'below one point' => ['0.50', '1.0000', 0],
            'zero amount' => ['0', '1.0000', 0],
            'negative amount' => ['-10', '1.0000', 0],
            'invalid amount' => ['abc', '1.0000', 0],
            'zero rate' => ['500', '0.0000', 0],
        ];
    }
}
