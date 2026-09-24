<?php
namespace Codilar\Suggestion\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\Registry;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Helper\Image as ImageHelper;

class RelatedCategoryProducts extends Template
{
    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;
    protected $categoryFactory;
    protected $registry;
    protected $imageHelper;

    /**
     * @param Context $context
     * @param CollectionFactory $productCollectionFactory
     * @param CategoryFactory $categoryFactory
     * @param Registry $registry
     * @param ImageHelper $imageHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        CategoryFactory $categoryFactory,
        Registry $registry,
        ImageHelper $imageHelper,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryFactory = $categoryFactory;
        $this->registry = $registry;
        $this->imageHelper = $imageHelper;
        parent::__construct($context, $data);
    }

    /**
     * @param $limit
     * @return array|\Magento\Catalog\Model\ResourceModel\Product\Collection
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCategoryProducts($limit = 4)
    {
        $currentProduct = $this->registry->registry('current_product');
        if (!$currentProduct) {
            return [];
        }

        // 1. Check if user navigated from a specific category
        $currentCategory = $this->registry->registry('current_category');
        $targetCategoryId = null;

        if ($currentCategory) {
            $targetCategoryId = $currentCategory->getId();
        } else {
            // 2. Fallback: Pick the last (deepest/most specific) category assigned to the product
            $categoryIds = $currentProduct->getCategoryIds();
            if (!empty($categoryIds)) {
                $targetCategoryId = end($categoryIds);
            }
        }

        if (!$targetCategoryId) {
            return [];
        }

        // Fetch category object dynamically via injected CategoryFactory
        $category = $this->categoryFactory->create()->load($targetCategoryId);

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*')
            ->addCategoryFilter($category)
            ->addFieldToFilter('entity_id', ['neq' => $currentProduct->getId()])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->setVisibility([Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_BOTH])
            ->setPageSize($limit);

        return $collection;
    }

    public function getImageUrl($product, $imageId = 'category_page_grid')
    {
        return $this->imageHelper->init($product, $imageId)->getUrl();
    }
}
