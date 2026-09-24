<?php

namespace Codilar\ProductDiscount\Plugin;

use Codilar\ProductDiscount\Block\ProductDiscount;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\Render;

class ProductPricePlugin
{
    private ProductDiscount $discountBlock;

    /**
     * @param ProductDiscount $discountBlock
     */
    public function __construct(ProductDiscount $discountBlock)
    {
        $this->discountBlock = $discountBlock;
    }

    /**
     * @param ListProduct $subject
     * @param $result
     * @param Product $product
     * @return string
     */
    public function afterGetProductPrice(ListProduct $subject, $result, Product $product)
    {
        return $result . $this->discountBlock->getDiscountHtml($product);
    }

    /**
     * @param Render $subject
     * @param $result
     * @param $priceCode
     * @param $saleableItem
     * @param array $arguments
     * @return mixed|string
     */

    public function afterRender(Render $subject, $result, $priceCode, $saleableItem, array $arguments = [])
    {
        if ($priceCode !== FinalPrice::PRICE_CODE) {
            return $result;
        }

        if (($arguments['zone'] ?? null) !== Render::ZONE_ITEM_VIEW) {
            return $result;
        }

        if (!$saleableItem instanceof Product) {
            return $result;
        }

        return $result . $this->discountBlock->getDiscountHtml($saleableItem);
    }
}
