<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Observer;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Order placement failed after the wallet was debited: post a compensating REDEMPTION_RELEASE entry. If the order
 * did get saved (the failure came later), nothing is released. Never throws, so the original error is the one the
 * customer sees; a release that fails here is picked up by the stuck-reservation sweeper.
 */
class ReleaseWallet implements ObserverInterface
{
    /**
     * @param WalletService $wallet
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly WalletService $wallet,
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();
        $key = (string)$order->getData('codilar_wallet_redeem_key');
        if ($key === '') {
            return;
        }
        try {
            $connection = $this->resource->getConnection();
            $saved = (int)$connection->fetchOne(
                $connection->select()
                    ->from($this->resource->getTableName('sales_order'), ['entity_id'])
                    ->where('increment_id = ?', (string)$order->getIncrementId())
            );
            if ($saved > 0) {
                return;
            }
            $this->wallet->credit(new WalletMovement(
                (int)$quote->getCustomerId(),
                (string)$quote->getCustomerEmail(),
                W::TYPE_REDEMPTION_RELEASE,
                (string)$order->getData('codilar_wallet_redeem_amount'),
                'Order #' . $order->getIncrementId() . ' was not placed; wallet amount returned',
                'release:' . $key,
                null,
                (string)$order->getIncrementId()
            ));
        } catch (\Throwable $e) {
            $this->logger->critical(
                'Loyalty wallet release failed for order ' . $order->getIncrementId() . ': ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
