<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Total\Invoice;

use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

/**
 * An invoice is built from the order's items at gross prices. The wallet-paid part is taken off here, earliest
 * invoice first, until the order's wallet amount has been invoiced in full (NFR-04).
 */
class Wallet extends AbstractTotal
{
    /**
     * @inheritDoc
     */
    public function collect(Invoice $invoice)
    {
        $order = $invoice->getOrder();
        $remaining = bcsub(
            Decimal::fromFloat($order->getData('codilar_wallet_amount')),
            Decimal::fromFloat($order->getData('codilar_wallet_invoiced')),
            Decimal::SCALE
        );
        $amount = '0.0000';
        if (Decimal::sign($remaining) > 0) {
            $gross = Decimal::fromFloat($invoice->getGrandTotal());
            $amount = Decimal::sign($gross) > 0 ? Decimal::min($remaining, $gross) : $amount;
        }
        if (Decimal::sign($amount) > 0) {
            $invoice->setGrandTotal((float)bcsub(Decimal::fromFloat($invoice->getGrandTotal()), $amount, 4));
            $invoice->setBaseGrandTotal((float)bcsub(Decimal::fromFloat($invoice->getBaseGrandTotal()), $amount, 4));
        }
        $invoice->setData('codilar_wallet_amount', $amount);
        return $this;
    }
}
