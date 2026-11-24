<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Points;

use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * The part of an order that earns points (FR-02): subtotal after discounts, before wallet, shipping and tax.
 */
class EligibleAmount
{
    /**
     * @param OrderInterface $order
     * @return string Decimal string, never negative
     */
    public function forOrder(OrderInterface $order): string
    {
        $amount = bcadd(
            Decimal::fromFloat($order->getBaseSubtotal()),
            Decimal::fromFloat($order->getBaseDiscountAmount()),
            Decimal::SCALE
        );
        return Decimal::sign($amount) > 0 ? $amount : '0.0000';
    }
}
