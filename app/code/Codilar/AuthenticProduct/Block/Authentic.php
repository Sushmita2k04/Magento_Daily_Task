<?php

declare(strict_types=1);

namespace Codilar\AuthenticProduct\Block;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

class Authentic extends Template
{
    /**
     * PHP 8.1+ Constructor Property Promotion
     */
    public function __construct(
        Context $context,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CatalogHelper $catalogHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get system config status for Authentic Product feature
     */
    public function isFeatureEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            'authentic_product/general/enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get current product safely via Catalog Helper (No Registry / No Deprecations)
     */
    public function getProduct(): ?Product
    {
        $product = $this->catalogHelper->getProduct();

        return ($product instanceof Product) ? $product : null;
    }

    /**
     * Check whether current product is marked as an Authentic Product.
     */
    public function isAuthentic(): bool
    {
        if (!$this->isFeatureEnabled()) {
            return false;
        }

        $product = $this->getProduct();

        if (!$product) {
            return false;
        }

        return (bool) $product->getData('is_authentic');
    }
}
