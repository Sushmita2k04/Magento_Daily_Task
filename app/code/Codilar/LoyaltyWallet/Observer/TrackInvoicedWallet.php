<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Observer;

use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Keeps the order's running "wallet invoiced" total in step with each invoice that is registered.
 */
class TrackInvoicedWallet implements ObserverInterface
{
    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $invoice = $observer->getEvent()->getInvoice();
        $order = $observer->getEvent()->getOrder();
        $amount = Decimal::fromFloat($invoice->getData('codilar_wallet_amount'));
        if (Decimal::sign($amount) > 0) {
            $order->setData('codilar_wallet_invoiced', bcadd(
                Decimal::fromFloat($order->getData('codilar_wallet_invoiced')),
                $amount,
                Decimal::SCALE
            ));
        }
    }
}
