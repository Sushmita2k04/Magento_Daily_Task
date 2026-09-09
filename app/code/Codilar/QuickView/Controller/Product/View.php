<?php

namespace Codilar\QuickView\Controller\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\CatalogInventory\Api\StockRegistryInterface;

class View extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly PriceHelper $priceHelper,
        private readonly ImageHelper $imageHelper,
        private readonly StockRegistryInterface $stockRegistry
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $productId = (int) $this->getRequest()->getParam('product_id');

            if (!$productId) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Product ID is missing.')
                ]);
            }

            $product = $this->productRepository->getById($productId);

            $stockItem = $this->stockRegistry->getStockItem($productId);

            $stockStatus = $stockItem->getIsInStock()
                ? __('In Stock')
                : __('Out of Stock');

            $imageUrl = $this->imageHelper
                ->init($product, 'product_base_image')
                ->getUrl();

            $price = $this->priceHelper->currency(
                $product->getFinalPrice(),
                true,
                false
            );

            return $result->setData([
                'success' => true,

                'product' => [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'sku' => $product->getSku(),
                    'price' => $price,
                    'image' => $imageUrl,
                    'stock' => $stockStatus,
                    'description' => $product->getShortDescription()
                        ?: $product->getDescription()
                ]
            ]);

        } catch (\Throwable $e) {

            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
