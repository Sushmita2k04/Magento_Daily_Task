<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model;

use Codilar\ProductLaunch\Api\Data\LaunchInterface;
use Codilar\ProductLaunch\Model\ResourceModel\Launch as ResourceModel;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

class Launch extends AbstractModel implements LaunchInterface, IdentityInterface
{
    public const CACHE_TAG = 'codilar_product_launch';
    public const CACHE_TAG_LIST = 'codilar_product_launch_list';

    /** @var string */
    protected $_cacheTag = self::CACHE_TAG;

    /** @var string */
    protected $_eventPrefix = 'codilar_product_launch';

    protected function _construct()
    {
        $this->_init(ResourceModel::class);
        $this->setIdFieldName(self::LAUNCH_ID);
    }

    public function getIdentities(): array
    {
        $tags = [self::CACHE_TAG_LIST];
        if ($this->getId()) {
            $tags[] = self::CACHE_TAG . '_' . $this->getId();
        }
        return $tags;
    }

    public function getLaunchId()
    {
        return $this->getData(self::LAUNCH_ID) === null ? null : (int)$this->getData(self::LAUNCH_ID);
    }

    public function setLaunchId($launchId)
    {
        return $this->setData(self::LAUNCH_ID, $launchId);
    }

    public function getTitle(): ?string
    {
        return $this->getData(self::TITLE);
    }

    public function setTitle(string $title): LaunchInterface
    {
        return $this->setData(self::TITLE, $title);
    }

    public function getUrlKey(): ?string
    {
        return $this->getData(self::URL_KEY);
    }

    public function setUrlKey(string $urlKey): LaunchInterface
    {
        return $this->setData(self::URL_KEY, $urlKey);
    }

    public function getProductId(): ?int
    {
        $id = $this->getData(self::PRODUCT_ID);
        return ($id === null || $id === '') ? null : (int)$id;
    }

    public function setProductId(?int $productId): LaunchInterface
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    public function getSku(): ?string
    {
        return $this->getData(self::SKU);
    }

    public function setSku(?string $sku): LaunchInterface
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getDescription(): ?string
    {
        return $this->getData(self::DESCRIPTION);
    }

    public function setDescription(?string $description): LaunchInterface
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function getBanner(): ?string
    {
        return $this->getData(self::BANNER) ?: null;
    }

    public function setBanner(?string $banner): LaunchInterface
    {
        return $this->setData(self::BANNER, $banner);
    }

    public function getStartAt(): ?string
    {
        return $this->getData(self::START_AT);
    }

    public function setStartAt(string $startAt): LaunchInterface
    {
        return $this->setData(self::START_AT, $startAt);
    }

    public function getEndAt(): ?string
    {
        return $this->getData(self::END_AT) ?: null;
    }

    public function setEndAt(?string $endAt): LaunchInterface
    {
        return $this->setData(self::END_AT, $endAt);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): LaunchInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function getStoreIds(): array
    {
        $ids = $this->getData(self::STORE_IDS);
        if (!is_array($ids) || !$ids) {
            return [0];
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function setStoreIds(array $storeIds): LaunchInterface
    {
        return $this->setData(self::STORE_IDS, $storeIds);
    }
}
