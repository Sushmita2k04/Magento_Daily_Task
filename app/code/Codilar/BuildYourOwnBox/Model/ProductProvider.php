<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Fetches products of a category at runtime and tells whether each is currently salable (MSI).
 */
class ProductProvider
{
    public const LISTING_LIMIT = 100;
    private const ALLOWED_TYPES = ['simple'];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StockResolverInterface $stockResolver,
        private readonly AreProductsSalableInterface $areProductsSalable,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Enabled, website-assigned simple products of the category, optionally limited to the given ids.
     * Each entry is ['product' => Product, 'salable' => bool, 'price' => float].
     *
     * @param int[]|null $ids
     * @return array<int, array{product: Product, salable: bool, price: float}>
     */
    public function getProducts(int $categoryId, ?array $ids = null): array
    {
        if ($categoryId < 1 || $ids === []) {
            return [];
        }
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        // Keep out-of-stock products so they can be shown as unavailable; salability is checked explicitly below.
        $collection->setFlag('has_stock_status_filter', true);
        $collection->addStoreFilter($this->storeManager->getStore())
            ->addAttributeToSelect(['name', 'short_description', 'small_image', 'price', 'weight', 'tax_class_id'])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('type_id', ['in' => self::ALLOWED_TYPES])
            ->addWebsiteFilter((int)$this->storeManager->getStore()->getWebsiteId())
            ->setOrder('name', 'ASC')
            ->setPageSize($ids === null ? self::LISTING_LIMIT : count($ids));
        if ($ids !== null) {
            $collection->addIdFilter($ids);
        }

        // Direct category assignment, read live (not from the category index), so admin changes apply immediately.
        $collection->getSelect()->join(
            ['byob_cp' => $collection->getTable('catalog_category_product')],
            'byob_cp.product_id = e.entity_id AND byob_cp.category_id = ' . $categoryId,
            []
        );

        $products = $collection->getItems();
        $salable = $this->getSalableFlags($products);

        $result = [];
        /** @var Product $product */
        foreach ($products as $product) {
            $result[(int)$product->getId()] = [
                'product' => $product,
                'salable' => $salable[$product->getSku()] ?? false,
                'price' => (float)$product->getPriceInfo()->getPrice('final_price')->getValue(),
            ];
        }
        return $result;
    }

    /**
     * @param Product[] $products
     * @return array<string, bool> keyed by SKU
     */
    private function getSalableFlags(array $products): array
    {
        if (!$products) {
            return [];
        }
        $skus = array_map(static fn(Product $p): string => (string)$p->getSku(), $products);
        $websiteCode = $this->storeManager->getWebsite()->getCode();
        $stockId = (int)$this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)
            ->getStockId();

        $flags = [];
        foreach ($this->areProductsSalable->execute(array_values($skus), $stockId) as $result) {
            $flags[$result->getSku()] = $result->isSalable();
        }
        return $flags;
    }
}
