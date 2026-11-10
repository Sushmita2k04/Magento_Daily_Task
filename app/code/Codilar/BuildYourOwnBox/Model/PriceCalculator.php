<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model;

use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * Computes subtotal, discount and final price of a box (all in base currency).
 */
class PriceCalculator
{
    public function __construct(private readonly PriceCurrencyInterface $priceCurrency)
    {
    }

    /**
     * @param float[] $coffeePrices
     * @return array{subtotal: float, discount: float, final_price: float}
     */
    public function calculate(array $coffeePrices, ?float $equipmentPrice, float $configuredDiscount): array
    {
        $subtotal = 0.0;
        foreach ($coffeePrices as $price) {
            $subtotal += max(0.0, (float)$price);
        }
        $subtotal += max(0.0, (float)$equipmentPrice);
        $subtotal = $this->priceCurrency->round($subtotal);

        // Negative or invalid discounts count as zero; the discount can never exceed the subtotal.
        $discount = $this->priceCurrency->round(min(max(0.0, $configuredDiscount), $subtotal));

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'final_price' => $this->priceCurrency->round(max(0.0, $subtotal - $discount)),
        ];
    }
}
