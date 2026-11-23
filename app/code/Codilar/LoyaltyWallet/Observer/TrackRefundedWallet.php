<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Observer;

use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Keeps the order's running "wallet refunded" total in step with each credit memo that is refunded.
 */
class TrackRefundedWallet implements ObserverInterface
{
    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $amount = Decimal::fromFloat($creditmemo->getData('codilar_wallet_amount'));
        if (Decimal::sign($amount) > 0) {
            $order = $creditmemo->getOrder();
            $order->setData('codilar_wallet_refunded', bcadd(
                Decimal::fromFloat($order->getData('codilar_wallet_refunded')),
                $amount,
                Decimal::SCALE
            ));
        }
    }
}
