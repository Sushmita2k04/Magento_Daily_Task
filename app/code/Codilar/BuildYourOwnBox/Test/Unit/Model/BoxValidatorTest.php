<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Test\Unit\Model;

use Codilar\BuildYourOwnBox\Model\BoxValidator;
use Codilar\BuildYourOwnBox\Model\Config;
use Codilar\BuildYourOwnBox\Model\PriceCalculator;
use Codilar\BuildYourOwnBox\Model\ProductProvider;
use Magento\Framework\DataObject;
use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BoxValidatorTest extends TestCase
{
    private Config|MockObject $config;
    private ProductProvider|MockObject $provider;
    private BoxValidator $validator;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getConfigurationError')->willReturn(null);
        $this->config->method('getCoffeeCount')->willReturn(3);
        $this->config->method('getCoffeeCategoryId')->willReturn(10);
        $this->config->method('getEquipmentCategoryId')->willReturn(20);
        $this->config->method('getDiscountAmount')->willReturn(100.0);
        $this->config->method('getMessageMaxLength')->willReturn(20);
        $this->config->method('isEnabled')->willReturn(true);

        $this->provider = $this->createMock(ProductProvider::class);

        $currency = $this->createMock(PriceCurrencyInterface::class);
        $currency->method('round')->willReturnCallback(static fn($v) => round((float)$v, 2));
        $store = $this->createMock(Store::class);
        $store->method('getBaseCurrencyCode')->willReturn('INR');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $date = $this->createMock(DateTime::class);
        $date->method('gmtDate')->willReturn('2026-01-01T00:00:00+00:00');

        $this->validator = new BoxValidator(
            $this->config,
            $this->provider,
            new PriceCalculator($currency),
            $storeManager,
            $date
        );
    }

    private function row(int $id, float $price = 100.0, bool $salable = true): array
    {
        $product = new DataObject(['id' => $id, 'name' => 'Item ' . $id, 'sku' => 'sku-' . $id, 'weight' => 0.5]);
        return ['product' => $product, 'salable' => $salable, 'price' => $price];
    }

    private function catalog(array $coffee, array $equipment = []): void
    {
        $this->provider->method('getProducts')->willReturnCallback(
            static function (int $category, ?array $ids) use ($coffee, $equipment): array {
                $source = $category === 10 ? $coffee : $equipment;
                return array_intersect_key($source, array_flip($ids ?? []));
            }
        );
    }

    private function coffeeCatalog(): array
    {
        return [1 => $this->row(1, 500), 2 => $this->row(2, 400), 3 => $this->row(3, 300),
            4 => $this->row(4), 5 => $this->row(5, 100, false)];
    }

    public function testValidBoxWithEquipmentAndDiscountSnapshot(): void
    {
        $this->catalog($this->coffeeCatalog(), [9 => $this->row(9, 200)]);
        $box = $this->validator->assemble(['coffee' => ['1', '2', '3'], 'equipment' => '9', 'message' => 'Happy Birthday!']);
        $data = $box->composition->toArray();
        $this->assertSame(1400.0, $data['subtotal']);
        $this->assertSame(100.0, $data['discount']);
        $this->assertSame(1300.0, $data['final_price']);
        $this->assertSame(100.0, $data['snapshot']['configured_discount']);
        $this->assertSame(2.0, $data['weight']);
        $this->assertSame('INR', $data['currency']);
        $this->assertCount(4, $box->products);
    }

    #[DataProvider('invalidSelectionProvider')]
    public function testRejectsInvalidSelections(array $input, string $expectedFragment): void
    {
        $this->catalog($this->coffeeCatalog(), [9 => $this->row(9, 200), 10 => $this->row(10, 50, false)]);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/' . $expectedFragment . '/');
        $this->validator->assemble($input);
    }

    public static function invalidSelectionProvider(): array
    {
        return [
            'fewer than three' => [['coffee' => [1, 2]], 'exactly 3'],
            'more than three' => [['coffee' => [1, 2, 3, 4]], 'exactly 3'],
            'duplicates' => [['coffee' => [1, 1, 2]], 'more than once'],
            'coffee outside category' => [['coffee' => [1, 2, 99]], 'not available'],
            'equipment outside category' => [['coffee' => [1, 2, 3], 'equipment' => 77], 'not available'],
            'multiple equipment' => [['coffee' => [1, 2, 3], 'equipment' => [9, 10]], 'Only one equipment'],
            'out of stock coffee' => [['coffee' => [1, 2, 5]], 'out of stock'],
            'out of stock equipment' => [['coffee' => [1, 2, 3], 'equipment' => 10], 'out of stock'],
            'coffee not an array' => [['coffee' => '1,2,3'], 'Invalid coffee'],
            'non numeric id' => [['coffee' => [1, 2, 'abc']], 'Invalid product'],
            'negative id' => [['coffee' => [1, 2, -3]], 'Invalid product'],
            'array element' => [['coffee' => [1, 2, [3]]], 'Invalid product'],
            'missing coffee' => [[], 'Invalid coffee'],
            'message too long' => [['coffee' => [1, 2, 3], 'message' => str_repeat('a', 21)], 'longer than 20'],
            'html message' => [['coffee' => [1, 2, 3], 'message' => '<script>alert(1)</script>'], 'HTML'],
            'img tag message' => [['coffee' => [1, 2, 3], 'message' => 'hi <img src=x onerror=1>'], 'HTML'],
            'bare angle bracket' => [['coffee' => [1, 2, 3], 'message' => '1 < 2'], 'HTML'],
            'array message' => [['coffee' => [1, 2, 3], 'message' => ['x']], 'Invalid gift message'],
        ];
    }

    public function testClientSuppliedPriceAndDiscountAreIgnored(): void
    {
        $this->catalog($this->coffeeCatalog());
        $box = $this->validator->assemble([
            'coffee' => [1, 2, 3], 'price' => 1, 'discount' => 99999, 'final_price' => 0,
        ]);
        $this->assertSame(1200.0, $box->composition->toArray()['subtotal']);
        $this->assertSame(1100.0, $box->composition->toArray()['final_price']);
    }

    public function testUnicodeMessageLengthCountsCharactersNotBytes(): void
    {
        $this->assertSame('☕☕☕☕☕☕☕☕☕☕☕☕☕☕☕☕☕☕☕☕', $this->validator->normalizeMessage(str_repeat('☕', 20)));
        $this->assertSame('नमस्ते दोस्त 🎁', $this->validator->normalizeMessage('नमस्ते दोस्त 🎁'));
        $this->expectException(LocalizedException::class);
        $this->validator->normalizeMessage(str_repeat('☕', 21));
    }

    public function testOrdinaryPunctuationIsPreserved(): void
    {
        $msg = "Hi! It's 1 & 2 :-)";
        $this->assertSame($msg, $this->validator->normalizeMessage($msg));
    }

    public function testInvalidUtf8IsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->validator->normalizeMessage("bad\xC3\x28");
    }
}
