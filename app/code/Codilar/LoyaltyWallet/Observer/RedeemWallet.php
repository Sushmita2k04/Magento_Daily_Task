<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Observer;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Just before the order is saved: copies the wallet amount from the quote to the order and takes it out of the
 * wallet under the customer's lock. If another order spent the balance first, this fails and no order is placed
 * (NFR-01, AC-03). If the order then fails to save, ReleaseWallet puts the money back.
 */
class RedeemWallet implements ObserverInterface
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
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();
        $amount = Decimal::fromFloat($quote->getData('codilar_wallet_amount'));
        $customerId = (int)$quote->getCustomerId();
        if (Decimal::sign($amount) <= 0 || $customerId < 1) {
            return;
        }
        if (!$order->getIncrementId()) {
            $quote->reserveOrderId();
            $order->setIncrementId($quote->getReservedOrderId());
        }

        $key = 'redeem:' . bin2hex(random_bytes(12));
        try {
            $this->wallet->debit(new WalletMovement(
                $customerId,
                (string)$quote->getCustomerEmail(),
                W::TYPE_DEBIT_REDEMPTION,
                $amount,
                'Used on order #' . $order->getIncrementId(),
                $key,
                null,
                (string)$order->getIncrementId()
            ));
        } catch (LocalizedException) {
            throw new LocalizedException(__(
                'Your wallet balance changed and no longer covers this order. '
                . 'Please review your cart and place the order again.'
            ));
        }
        $order->setData('codilar_wallet_amount', $amount);
        $order->setData('codilar_wallet_redeem_key', $key);
        $order->setData('codilar_wallet_redeem_amount', $amount);
    }
}
