<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_ENABLED = 'codilar_productlaunch/general/enabled';
    public const XML_PAGE_TITLE = 'codilar_productlaunch/general/page_title';
    public const XML_COMPLETED_LIMIT = 'codilar_productlaunch/general/completed_limit';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getPageTitle(?int $storeId = null): string
    {
        $title = trim((string)$this->scopeConfig->getValue(self::XML_PAGE_TITLE, ScopeInterface::SCOPE_STORE, $storeId));
        return $title !== '' ? $title : 'Product Launches';
    }

    public function getCompletedLimit(?int $storeId = null): int
    {
        $limit = (int)$this->scopeConfig->getValue(self::XML_COMPLETED_LIMIT, ScopeInterface::SCOPE_STORE, $storeId);
        return $limit > 0 ? $limit : 6;
    }
}
