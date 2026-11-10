<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Test\Unit\Model;

use Codilar\BuildYourOwnBox\Model\PriceCalculator;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use PHPUnit\Framework\TestCase;

class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        $currency = $this->createMock(PriceCurrencyInterface::class);
        $currency->method('round')->willReturnCallback(static fn($v) => round((float)$v, 2));
        $this->calculator = new PriceCalculator($currency);
    }

    public function testSpecExample(): void
    {
        $r = $this->calculator->calculate([500, 400, 300], 200.0, 100.0);
        $this->assertSame(['subtotal' => 1400.0, 'discount' => 100.0, 'final_price' => 1300.0], $r);
    }

    public function testNoEquipment(): void
    {
        $r = $this->calculator->calculate([500, 400, 300], null, 100.0);
        $this->assertSame(1200.0, $r['subtotal']);
        $this->assertSame(1100.0, $r['final_price']);
    }

    public function testNegativeDiscountIsZero(): void
    {
        $r = $this->calculator->calculate([10, 10, 10], null, -50.0);
        $this->assertSame(0.0, $r['discount']);
        $this->assertSame(30.0, $r['final_price']);
    }

    public function testDiscountCannotExceedSubtotal(): void
    {
        $r = $this->calculator->calculate([10, 10, 10], null, 500.0);
        $this->assertSame(30.0, $r['discount']);
        $this->assertSame(0.0, $r['final_price']);
    }

    public function testRoundingToCurrencyPrecision(): void
    {
        $r = $this->calculator->calculate([0.333, 0.333, 0.333], null, 0.0);
        $this->assertSame(1.0, $r['subtotal']);
    }
}
