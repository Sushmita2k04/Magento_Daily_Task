<?php

namespace Codilar\ColorSwatchImage\Controller\Ajax;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Image extends Action
{
    private JsonFactory $resultJsonFactory;
    private ProductRepositoryInterface $productRepository;
    private Configurable $configurableType;
    private ImageHelper $imageHelper;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ProductRepositoryInterface $productRepository,
        Configurable $configurableType,
        ImageHelper $imageHelper
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->productRepository = $productRepository;
        $this->configurableType = $configurableType;
        $this->imageHelper = $imageHelper;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $productId = (int) $this->getRequest()->getParam('product_id');
        $colorOptionId = (int) $this->getRequest()->getParam('color');

        if (!$productId || !$colorOptionId) {
            return $result->setData([
                'success' => false,
                'message' => 'Product ID or color option ID is missing.'
            ]);
        }

        try {
            $parentProduct = $this->productRepository->getById($productId);

            $configurableAttributes = $this->configurableType
                ->getConfigurableAttributes($parentProduct);

            $colorAttributeCode = null;

            foreach ($configurableAttributes as $attribute) {
                $productAttribute = $attribute->getProductAttribute();

                if ($productAttribute->getAttributeCode() === 'color') {
                    $colorAttributeCode = $productAttribute->getAttributeCode();
                    break;
                }
            }

            if (!$colorAttributeCode) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Color attribute is not configurable.'
                ]);
            }

            $children = $this->configurableType->getUsedProducts($parentProduct);

            foreach ($children as $child) {
                if ((int) $child->getData($colorAttributeCode) !== $colorOptionId) {
                    continue;
                }

                if (!$child->getImage() || $child->getImage() === 'no_selection') {
                    continue;
                }

                $imageUrl = $this->imageHelper
                    ->init($child, 'category_page_grid')
                    ->getUrl();

                return $result->setData([
                    'success' => true,
                    'image' => $imageUrl,
                    'product_id' => (int) $child->getId()
                ]);
            }

            return $result->setData([
                'success' => false,
                'message' => 'No child product found for this color.'
            ]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Unable to load color image.'
            ]);
        }
    }
}
