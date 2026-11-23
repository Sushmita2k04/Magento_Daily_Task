<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Observer;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Once a refunded credit memo is saved (inside the same transaction as the refund), returns its wallet share to the customer's wallet. The key is the credit
 * memo id, so repeated saves credit once. The rest of the refund goes through the payment method as usual.
 */
class CreditWalletOnRefund implements ObserverInterface
{
    /**
     * @param WalletService $wallet
     */
    public function __construct(private readonly WalletService $wallet)
    {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $amount = Decimal::fromFloat($creditmemo->getData('codilar_wallet_amount'));
        $order = $creditmemo->getOrder();
        if (Decimal::sign($amount) <= 0
            || (int)$creditmemo->getState() !== Creditmemo::STATE_REFUNDED
            || !$creditmemo->getId()
            || !$order->getCustomerId()
        ) {
            return;
        }
        $this->wallet->credit(new WalletMovement(
            (int)$order->getCustomerId(),
            (string)$order->getCustomerEmail(),
            W::TYPE_CREDIT_REFUND,
            $amount,
            'Refund for order #' . $order->getIncrementId(),
            'refund:cm:' . $creditmemo->getId(),
            (int)$order->getEntityId(),
            (string)$order->getIncrementId(),
            (int)$creditmemo->getId()
        ));
    }
}
