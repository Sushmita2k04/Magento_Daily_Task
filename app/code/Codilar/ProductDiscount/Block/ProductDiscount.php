<?php

namespace Codilar\ProductDiscount\Block;

use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template;

class ProductDiscount extends Template
{
    private PriceHelper $priceHelper;

    /**
     * @param Template\Context $context
     * @param PriceHelper $priceHelper
     * @param array $data
     */
    public function __construct(Template\Context $context, PriceHelper $priceHelper, array $data = [])
    {
        $this->priceHelper = $priceHelper;
        parent::__construct($context, $data);
    }

    public function getDiscountHtml(Product $product): string
    {
        try {
            $regularPrice = (float) $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
            $finalPrice = (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();

            if ($regularPrice <= 0 || $finalPrice >= $regularPrice) {
                return '';
            }

            $discountPercent = round((($regularPrice - $finalPrice) / $regularPrice) * 100);

            if ($discountPercent <= 0) {
                return '';
            }

            $regularPriceHtml = $this->priceHelper->currency($regularPrice, true, false);

            return $this->setTemplate('Codilar_ProductDiscount::product/discount.phtml')
                ->setRegularPrice($regularPriceHtml)
                ->setDiscountPercent($discountPercent)
                ->toHtml();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
