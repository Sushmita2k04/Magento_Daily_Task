<?php

namespace Codilar\CampaignWidget\Block\Widget;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Magento\Wishlist\Helper\Data as WishlistHelper;

class Campaign extends Template implements BlockInterface
{
    protected $_template = 'widget/campaign.phtml';

    private CollectionFactory $productCollectionFactory;

    private CategoryRepositoryInterface $categoryRepository;

    private ImageHelper $imageHelper;

    private PriceHelper $priceHelper;

    private WishlistHelper $wishlistHelper;

   public function __construct(
    Template\Context $context,
    CollectionFactory $productCollectionFactory,
    CategoryRepositoryInterface $categoryRepository,
    ImageHelper $imageHelper,
    PriceHelper $priceHelper,
    WishlistHelper $wishlistHelper,
    array $data = []
) {
    $this->productCollectionFactory = $productCollectionFactory;
    $this->categoryRepository = $categoryRepository;
    $this->imageHelper = $imageHelper;
    $this->priceHelper = $priceHelper;
    $this->wishlistHelper = $wishlistHelper;

    error_log('=== CODILAR CAMPAIGN BLOCK CREATED ===');
    error_log('Campaign Title: ' . (string) ($data['campaign_title'] ?? 'NOT SET'));
    error_log('Category ID: ' . (string) ($data['category_id'] ?? 'NOT SET'));
    error_log('Product Quantity: ' . (string) ($data['product_quantity'] ?? 'NOT SET'));

    parent::__construct($context, $data);
}

    public function getCampaignTitle(): string
    {
        return (string) $this->getData('campaign_title');
    }

    public function getCategoryId(): int
    {
        return (int) $this->getData('category_id');
    }

    public function getProductQuantity(): int
    {
        return max(0, (int) $this->getData('product_quantity'));
    }

    public function getProducts(): array
    {
        $categoryId = $this->getCategoryId();
        $quantity = $this->getProductQuantity();

        error_log('=== Campaign Widget Debug START ===');

        error_log(
            'Campaign Title: ' . $this->getCampaignTitle()
        );

        error_log(
            'Category ID: ' . $categoryId
        );

        error_log(
            'Product Quantity: ' . $quantity
        );

        if ($categoryId <= 0 || $quantity <= 0) {
            error_log(
                'Campaign Widget: Invalid category ID or quantity'
            );

            error_log('=== Campaign Widget Debug END ===');

            return [];
        }

        try {
            $category = $this->categoryRepository->get($categoryId);

            error_log(
                'Category Loaded ID: ' . $category->getId()
            );

            error_log(
                'Category Name: ' . $category->getName()
            );

            error_log(
                'Category Active: ' . (int) $category->getIsActive()
            );
        } catch (NoSuchEntityException $exception) {
            error_log(
                'Campaign Widget: Category not found: ' . $categoryId
            );

            error_log(
                'Exception: ' . $exception->getMessage()
            );

            error_log('=== Campaign Widget Debug END ===');

            return [];
        }

        if (!$category->getId() || !$category->getIsActive()) {
            error_log(
                'Campaign Widget: Category is missing or inactive'
            );

            error_log('=== Campaign Widget Debug END ===');

            return [];
        }

        $collection = $this->productCollectionFactory->create();

        $collection->addAttributeToSelect([
            'name',
            'price',
            'special_price',
            'special_from_date',
            'special_to_date',
            'small_image',
            'thumbnail',
            'image',
            'url_key',
            'sku',
            'status',
            'visibility'
        ]);

        $collection->addCategoryFilter($category);

        $collection->addAttributeToFilter(
            'status',
            Status::STATUS_ENABLED
        );

        $collection->setVisibility([
            Visibility::VISIBILITY_IN_CATALOG,
            Visibility::VISIBILITY_BOTH,
        ]);

        $collection->setPageSize($quantity);

        $collection->setCurPage(1);

        $products = $collection->getItems();

        error_log(
            'Products Found: ' . count($products)
        );

        foreach ($products as $product) {
            error_log(
                'Product: ID=' . $product->getId()
                . ' | SKU=' . $product->getSku()
                . ' | Name=' . $product->getName()
            );
        }

        error_log('=== Campaign Widget Debug END ===');

        return $products;
    }

    public function getProductImage(Product $product): string
    {
        return $this->imageHelper
            ->init($product, 'product_page_image_small')
            ->getUrl();
    }

    public function getProductName(Product $product): string
    {
        return (string) $product->getName();
    }

    public function getProductUrl(Product $product): string
    {
        return (string) $product->getProductUrl();
    }

    public function getProductSku(Product $product): string
    {
        return (string) $product->getSku();
    }

    public function getRegularPrice(Product $product): float
    {
        return (float) $product->getPrice();
    }

    public function getFinalPrice(Product $product): float
    {
        return (float) $product->getFinalPrice();
    }

    public function getFormattedPrice(float $price): string
    {
        return $this->priceHelper->currency(
            $price,
            true,
            false
        );
    }

    public function getDiscountPercentage(Product $product): int
    {
        $regularPrice = $this->getRegularPrice($product);

        $finalPrice = $this->getFinalPrice($product);

        if ($regularPrice <= 0 || $finalPrice >= $regularPrice) {
            return 0;
        }

        return (int) round(
            (($regularPrice - $finalPrice) / $regularPrice) * 100
        );
    }

    public function canAddToWishlist(): bool
    {
        return $this->wishlistHelper->isAllow();
    }

    public function getWishlistParams(Product $product): string
    {
        return (string) $this->wishlistHelper->getAddParams($product);
    }

    public function getBanner(): string
    {
        return (string) $this->getData('banner');
    }

    public function getBannerLink(): string
    {
        return (string) $this->getData('banner_link');
    }

    public function getVoucherLabel(): string
    {
        return (string) $this->getData('voucher_label');
    }

    public function getVoucherTitle(): string
    {
        return (string) $this->getData('voucher_title');
    }

    public function getVoucherDescription(): string
    {
        return (string) $this->getData('voucher_description');
    }

    public function getVoucherCode(): string
    {
        return (string) $this->getData('voucher_code');
    }

    public function getMinimumSpend(): string
    {
        return (string) $this->getData('minimum_spend');
    }

    public function isEnabled(): bool
    {
        return (bool) $this->getData('enabled');
    }
}