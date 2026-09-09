<?php

declare(strict_types=1);

namespace Codilar\FreeShippingProgress\Block;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;

class Progress extends Template
{
    private const PRODUCT_LIMIT = 10;

    private const REWARDS = [
        [
            'amount' => 500,
            'title' => 'Free Delivery',
            'subtitle' => 'Auto applied',
            'type' => 'shipping'
        ],
        [
            'amount' => 750,
            'title' => '$50 OFF',
            'subtitle' => 'View ›',
            'type' => 'coupon'
        ],
        [
            'amount' => 1000,
            'title' => '$100 OFF',
            'subtitle' => 'Coupon',
            'type' => 'coupon'
        ],
        [
            'amount' => 1500,
            'title' => '$150 OFF',
            'subtitle' => 'Coupon',
            'type' => 'coupon'
        ]
    ];

    public function __construct(
        Template\Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly Image $imageHelper,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getRewards(): array
    {
        return self::REWARDS;
    }

    public function getMaxRewardAmount(): float
    {
        if (self::REWARDS === []) {
            return 0.0;
        }

        $lastReward = self::REWARDS[array_key_last(self::REWARDS)];

        return (float) $lastReward['amount'];
    }

    public function getInitialSubtotal(): float
    {
        return (float) $this->checkoutSession
            ->getQuote()
            ->getSubtotal();
    }

    public function getRewardsJson(): string
    {
        return $this->json->serialize(
            $this->getRewards()
        );
    }

    public function getProductsJson(): string
    {
        $quote = $this->checkoutSession->getQuote();

        $categoryIds = [];
        $cartProductIds = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();

            if (!$product || !$product->getId()) {
                continue;
            }

            $productId = (int) $product->getId();

            $cartProductIds[] = $productId;

            $categoryIds = array_merge(
                $categoryIds,
                $product->getCategoryIds()
            );
        }

        $categoryIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $categoryIds)
                )
            )
        );

        $cartProductIds = array_values(
            array_unique($cartProductIds)
        );

        if (!$categoryIds) {
            return $this->json->serialize([]);
        }

        $collection = $this->productCollectionFactory->create();

        $collection->addAttributeToSelect([
            'name',
            'price',
            'special_price',
            'small_image'
        ]);

        $collection->addAttributeToFilter(
            'status',
            Status::STATUS_ENABLED
        );

        $collection->addAttributeToFilter(
            'visibility',
            [
                'in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH
                ]
            ]
        );

        if ($cartProductIds) {
            $collection->addAttributeToFilter(
                'entity_id',
                [
                    'nin' => $cartProductIds
                ]
            );
        }

        $categoryProductSelect = $collection
            ->getConnection()
            ->select()
            ->from(
                ['category_product' => 'catalog_category_product'],
                ['product_id']
            )
            ->where(
                'category_product.category_id IN (?)',
                $categoryIds
            );

        $collection->getSelect()->where(
            'e.entity_id IN (?)',
            $categoryProductSelect
        );

        $collection
            ->addUrlRewrite()
            ->setOrder(
                'entity_id',
                'DESC'
            )
            ->setPageSize(
                self::PRODUCT_LIMIT
            );

        $products = [];

        /** @var Product $product */
        foreach ($collection as $product) {
            $products[] = [
                'id' => (int) $product->getId(),
                'name' => (string) $product->getName(),
                'url' => (string) $product->getProductUrl(),
                'image' => $this->imageHelper
                    ->init(
                        $product,
                        'product_small_image'
                    )
                    ->getUrl(),
                'regular_price' => (float) $product->getPrice(),
                'final_price' => (float) $product->getFinalPrice()
            ];
        }

        return $this->json->serialize($products);
    }
}
