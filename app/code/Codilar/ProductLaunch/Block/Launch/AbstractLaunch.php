<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Block\Launch;

use Codilar\ProductLaunch\Api\Data\LaunchInterface;
use Codilar\ProductLaunch\Model\BannerManager;
use Codilar\ProductLaunch\Model\DateTimeConverter;
use Codilar\ProductLaunch\Model\Launch;
use Codilar\ProductLaunch\Model\Status;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

abstract class AbstractLaunch extends Template implements IdentityInterface
{
    public function __construct(
        Context $context,
        protected readonly BannerManager $bannerManager,
        protected readonly DateTimeConverter $dateTimeConverter,
        protected readonly Status $statusModel,
        protected readonly PriceCurrencyInterface $priceCurrency,
        protected readonly ImageHelper $imageHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int,array{launch:LaunchInterface,product:Product,status:string}>
     */
    abstract protected function getEntries(): array;

    public function getIdentities(): array
    {
        $tags = [Launch::CACHE_TAG_LIST];
        foreach ($this->getEntries() as $entry) {
            $tags[] = Launch::CACHE_TAG . '_' . $entry['launch']->getId();
            $tags[] = Product::CACHE_TAG . '_' . $entry['product']->getId();
        }
        return array_unique($tags);
    }

    public function getStatusLabel(string $status): string
    {
        return (string)($this->statusModel->getLabels()[$status] ?? '');
    }

    public function getBannerUrl(LaunchInterface $launch): ?string
    {
        return $this->bannerManager->getUrl($launch->getBanner());
    }

    public function getStartTimestamp(LaunchInterface $launch): int
    {
        return (int)$this->statusModel->toTimestamp($launch->getStartAt());
    }

    public function getEndTimestamp(LaunchInterface $launch): int
    {
        return (int)$this->statusModel->toTimestamp($launch->getEndAt());
    }

    /** Formatted in the timezone of the current store view. */
    public function formatLaunchDate(?string $utc): string
    {
        $date = $this->dateTimeConverter->utcToStore($utc, (int)$this->_storeManager->getStore()->getId());
        return $date ? $date->format('M j, Y, g:i A T') : '';
    }

    public function getLaunchUrl(LaunchInterface $launch): string
    {
        return $this->getUrl('launches/' . $launch->getUrlKey());
    }

    public function getProductUrl(Product $product): ?string
    {
        return in_array((int)$product->getVisibility(), [Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_BOTH, Visibility::VISIBILITY_IN_SEARCH], true)
            ? $product->getProductUrl()
            : null;
    }

    public function getProductPrice(Product $product): string
    {
        $price = $product->getFinalPrice() ?: $product->getPrice();
        return $price ? $this->priceCurrency->format((float)$price, false) : '';
    }

    public function getProductImageUrl(Product $product): string
    {
        return $this->imageHelper->init($product, 'product_small_image')->getUrl();
    }

    public function getDescriptionExcerpt(LaunchInterface $launch, int $length = 160): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$launch->getDescription())));
        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }
}
