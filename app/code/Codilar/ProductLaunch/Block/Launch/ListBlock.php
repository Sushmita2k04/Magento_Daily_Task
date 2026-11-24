<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Block\Launch;

use Codilar\ProductLaunch\Model\BannerManager;
use Codilar\ProductLaunch\Model\DateTimeConverter;
use Codilar\ProductLaunch\Model\Status;
use Codilar\ProductLaunch\Model\Storefront\Provider;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template\Context;

class ListBlock extends AbstractLaunch
{
    private ?array $sections = null;

    public function __construct(
        Context $context,
        BannerManager $bannerManager,
        DateTimeConverter $dateTimeConverter,
        Status $statusModel,
        PriceCurrencyInterface $priceCurrency,
        ImageHelper $imageHelper,
        private readonly Provider $provider,
        array $data = []
    ) {
        parent::__construct($context, $bannerManager, $dateTimeConverter, $statusModel, $priceCurrency, $imageHelper, $data);
    }

    public function getSections(): array
    {
        return $this->sections ??= $this->provider->getSections();
    }

    protected function getEntries(): array
    {
        return array_merge(...array_values($this->getSections()));
    }

    public function hasEntries(): bool
    {
        return (bool)$this->getEntries();
    }
}
