<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Test\Unit\Model;

use Codilar\BuildYourOwnBox\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private function config(array $values): Config
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(
            static fn(string $path) => $values[substr($path, strlen('codilar_byob/general/'))] ?? null
        );
        return new Config($scope);
    }

    public function testDefaultsAndInvalidValuesFallBackSafely(): void
    {
        $c = $this->config(['coffee_count' => 'abc', 'discount_amount' => '-5', 'message_max_length' => '0']);
        $this->assertSame(3, $c->getCoffeeCount());
        $this->assertSame(0.0, $c->getDiscountAmount());
        $this->assertSame(200, $c->getMessageMaxLength());
        $this->assertSame(0, $c->getCoffeeCategoryId());
    }

    public function testValuesAreClamped(): void
    {
        $c = $this->config(['coffee_count' => '99', 'message_max_length' => '99999', 'discount_amount' => '12.5']);
        $this->assertSame(10, $c->getCoffeeCount());
        $this->assertSame(1000, $c->getMessageMaxLength());
        $this->assertSame(12.5, $c->getDiscountAmount());
    }

    public function testConfigurationErrors(): void
    {
        $this->assertNotNull($this->config(['enabled' => '0'])->getConfigurationError());
        $this->assertNotNull($this->config(['enabled' => '1'])->getConfigurationError());
        $this->assertNotNull(
            $this->config(['enabled' => '1', 'coffee_category_id' => '5', 'equipment_category_id' => '5'])
                ->getConfigurationError()
        );
        $this->assertNull(
            $this->config(['enabled' => '1', 'coffee_category_id' => '5', 'equipment_category_id' => '6'])
                ->getConfigurationError()
        );
    }
}
