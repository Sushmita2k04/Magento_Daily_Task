<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model\Product;

use Codilar\BuildYourOwnBox\Model\BoxComposition;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\Price as BasePrice;

/**
 * The box price is the server-computed final price stored in the box's own composition snapshot,
 * so later configuration changes never re-price a box that is already in a cart.
 */
class Price extends BasePrice
{
    public function getPrice($product)
    {
        $composition = $product instanceof Product ? BoxComposition::fromProduct($product) : null;
        return $composition ? $composition->getFinalPrice() : 0.0;
    }

    public function getFinalPrice($qty, $product)
    {
        $price = $this->getPrice($product);
        $product->setFinalPrice($price);
        return $price;
    }
}
