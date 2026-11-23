<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model\Storefront;

use Codilar\ProductLaunch\Api\Data\LaunchInterface;
use Codilar\ProductLaunch\Api\LaunchRepositoryInterface;
use Codilar\ProductLaunch\Model\Config;
use Codilar\ProductLaunch\Model\ResourceModel\Launch\CollectionFactory;
use Codilar\ProductLaunch\Model\Status;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Storefront read model: only enabled launches, in this store view, with an available catalog product.
 * Status is always calculated from the dates relative to "now" (UTC).
 */
class Provider
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly LaunchRepositoryInterface $repository,
        private readonly Status $status,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array{upcoming:array,live:array,completed:array}
     */
    public function getSections(?int $storeId = null, ?\DateTimeInterface $now = null): array
    {
        $storeId ??= (int)$this->storeManager->getStore()->getId();
        $sections = [Status::UPCOMING => [], Status::LIVE => [], Status::COMPLETED => []];
        if (!$this->config->isEnabled($storeId)) {
            return $sections;
        }
        $nowUtc = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        foreach ([Status::UPCOMING, Status::LIVE, Status::COMPLETED] as $status) {
            $collection = $this->collectionFactory->create();
            $collection->addActiveFilter()->addStoreFilter($storeId)->addStatusFilter($status, $nowUtc);
            // products can be unavailable, so over-fetch a little for the limited section
            $limit = $status === Status::COMPLETED ? $this->config->getCompletedLimit($storeId) : 0;
            $order = $status === Status::COMPLETED ? 'DESC' : 'ASC';
            $collection->setOrder($status === Status::COMPLETED ? 'end_at' : 'start_at', $order)
                ->setOrder('launch_id', 'ASC');
            $launches = $collection->getItems();
            $products = $this->loadProducts($launches, $storeId);
            foreach ($launches as $launch) {
                $product = $products[(int)$launch->getProductId()] ?? null;
                if ($product === null) {
                    continue; // deleted, disabled or not in this website => never shown
                }
                $sections[$status][] = $this->entry($launch, $product, $status);
                if ($limit && count($sections[$status]) >= $limit) {
                    break;
                }
            }
        }
        return $sections;
    }

    /**
     * @return array{launch:LaunchInterface,product:Product,status:string}
     * @throws NoSuchEntityException when disabled, wrong store, unavailable product, invalid dates, unknown key
     */
    public function getByUrlKey(string $urlKey, ?int $storeId = null, ?\DateTimeInterface $now = null): array
    {
        $storeId ??= (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $urlKey)) {
            throw new NoSuchEntityException(__('Launch not found.'));
        }
        $launch = $this->repository->getByUrlKey($urlKey);
        $stores = $launch->getStoreIds();
        if (!$launch->getIsActive() || (!in_array(0, $stores, true) && !in_array($storeId, $stores, true))) {
            throw new NoSuchEntityException(__('Launch not found.'));
        }
        try {
            $status = $this->status->calculate($launch->getStartAt(), $launch->getEndAt(), $now);
        } catch (\InvalidArgumentException) {
            throw new NoSuchEntityException(__('Launch not found.'));
        }
        $product = $this->loadProducts([$launch], $storeId)[(int)$launch->getProductId()] ?? null;
        if ($product === null) {
            throw new NoSuchEntityException(__('Launch not found.'));
        }
        return $this->entry($launch, $product, $status);
    }

    private function entry(LaunchInterface $launch, Product $product, string $status): array
    {
        return ['launch' => $launch, 'product' => $product, 'status' => $status];
    }

    /**
     * @param LaunchInterface[] $launches
     * @return array<int,Product>
     */
    private function loadProducts(array $launches, int $storeId): array
    {
        $ids = [];
        foreach ($launches as $launch) {
            if ($launch->getProductId()) {
                $ids[] = $launch->getProductId();
            }
        }
        if (!$ids) {
            return [];
        }
        $collection = $this->productCollectionFactory->create();
        // sold-out products must not hide their launch announcement
        $collection->setFlag('has_stock_status_filter', true);
        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addIdFilter(array_unique($ids))
            ->addAttributeToSelect(['name', 'small_image', 'url_key', 'visibility', 'special_price', 'special_from_date', 'special_to_date', 'price'])
            ->addAttributeToFilter('status', ProductStatus::STATUS_ENABLED)
            ->addUrlRewrite();
        $result = [];
        foreach ($collection as $product) {
            $result[(int)$product->getId()] = $product;
        }
        return $result;
    }
}
