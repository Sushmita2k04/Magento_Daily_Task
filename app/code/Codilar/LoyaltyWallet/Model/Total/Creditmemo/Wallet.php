<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Total\Creditmemo;

use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

/**
 * Splits a credit memo between the wallet and the payment method (NFR-04). The wallet gets back the same share of
 * the refund as it paid of the invoiced amount; the memo's grand total, which drives the payment-method refund, is
 * reduced by that share. A full refund returns everything still outstanding.
 */
class Wallet extends AbstractTotal
{
    /**
     * @param Config $config
     * @param array $data
     */
    public function __construct(private readonly Config $config, array $data = [])
    {
        parent::__construct($data);
    }

    /**
     * @inheritDoc
     */
    public function collect(Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $amount = '0.0000';
        $invoiced = Decimal::fromFloat($order->getData('codilar_wallet_invoiced'));
        $remaining = bcsub($invoiced, Decimal::fromFloat($order->getData('codilar_wallet_refunded')), Decimal::SCALE);
        $gross = Decimal::fromFloat($creditmemo->getGrandTotal());

        if ($this->config->isRefundToWallet((int)$order->getStoreId())
            && Decimal::sign($remaining) > 0
            && Decimal::sign($gross) > 0
        ) {
            $paidGross = bcadd(Decimal::fromFloat($order->getTotalInvoiced()), $invoiced, Decimal::SCALE);
            $refundedGross = bcadd(
                Decimal::fromFloat($order->getTotalRefunded()),
                Decimal::fromFloat($order->getData('codilar_wallet_refunded')),
                Decimal::SCALE
            );
            $share = bccomp(bcadd($refundedGross, $gross, Decimal::SCALE), $paidGross, Decimal::SCALE) >= 0
                ? $remaining
                : bcdiv(bcmul($gross, $invoiced, 8), $paidGross, Decimal::SCALE);
            $amount = Decimal::min(Decimal::min($share, $remaining), $gross);
        }
        if (Decimal::sign($amount) > 0) {
            $creditmemo->setGrandTotal((float)bcsub($gross, $amount, Decimal::SCALE));
            $creditmemo->setBaseGrandTotal(
                (float)bcsub(Decimal::fromFloat($creditmemo->getBaseGrandTotal()), $amount, Decimal::SCALE)
            );
        }
        $creditmemo->setData('codilar_wallet_amount', $amount);
        return $this;
    }
}
