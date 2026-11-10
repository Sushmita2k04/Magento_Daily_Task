<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Block;

use Codilar\BuildYourOwnBox\Model\Config;
use Codilar\BuildYourOwnBox\Model\ProductProvider;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\DataObject\IdentityInterface;

/**
 * Storefront data for the assembly page. Contains no customer-specific data, so the page is fully cacheable;
 * it is tagged with the listed products so stock/price changes refresh it.
 */
class BoxPage extends Template implements IdentityInterface
{
    private ?array $coffee = null;
    private ?array $equipment = null;

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly ProductProvider $productProvider,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly FormatInterface $localeFormat,
        private readonly ImageHelper $imageHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getErrorMessage(): ?string
    {
        $error = $this->config->getConfigurationError();
        return $error === null ? null : (string)__($error);
    }

    public function getCoffee(): array
    {
        return $this->coffee ??= $this->prepare($this->config->getCoffeeCategoryId());
    }

    public function getEquipment(): array
    {
        return $this->equipment ??= $this->prepare($this->config->getEquipmentCategoryId());
    }

    public function getJsConfig(): array
    {
        return [
            'coffeeCount' => $this->config->getCoffeeCount(),
            'maxMessageLength' => $this->config->getMessageMaxLength(),
            'discount' => $this->priceCurrency->convertAndRound($this->config->getDiscountAmount()),
            'priceFormat' => $this->localeFormat->getPriceFormat(),
            'addUrl' => $this->getUrl('build-your-own-box/cart/add'),
        ];
    }

    public function formatPrice(float $amount): string
    {
        return $this->priceCurrency->format($amount, false);
    }

    public function getIdentities(): array
    {
        $tags = [];
        foreach ([$this->getCoffee(), $this->getEquipment()] as $rows) {
            foreach ($rows as $row) {
                $tags[] = Product::CACHE_TAG . '_' . $row['id'];
            }
        }
        foreach ([$this->config->getCoffeeCategoryId(), $this->config->getEquipmentCategoryId()] as $categoryId) {
            $tags[] = \Magento\Catalog\Model\Category::CACHE_TAG . '_' . $categoryId;
        }
        return array_values(array_unique($tags));
    }

    private function prepare(int $categoryId): array
    {
        if ($this->config->getConfigurationError() !== null) {
            return [];
        }
        $rows = [];
        foreach ($this->productProvider->getProducts($categoryId) as $id => $row) {
            /** @var Product $product */
            $product = $row['product'];
            $displayPrice = $this->priceCurrency->convertAndRound($row['price']);
            $rows[] = [
                'id' => $id,
                'name' => (string)$product->getName(),
                'description' => trim(strip_tags((string)$product->getShortDescription())),
                'image' => $this->imageHelper->init($product, 'category_page_grid')->getUrl(),
                'price' => $displayPrice,
                'priceHtml' => $this->formatPrice($displayPrice),
                'salable' => $row['salable'] && $row['price'] > 0,
            ];
        }
        return $rows;
    }
}
