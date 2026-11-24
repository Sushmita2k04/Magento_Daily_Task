<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Observer;

use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\Model\Points\EligibleAmount;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Awards points when an order is placed (FR-01, FR-02). The base is the subtotal after discounts, before wallet,
 * shipping and tax. Never throws: a failure here must not stop the customer's order; the sweeper retries it, and
 * accrual is idempotent per order.
 */
class AccruePoints implements ObserverInterface
{
    /**
     * @param PointsManagementInterface $points
     * @param EligibleAmount $eligibleAmount
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PointsManagementInterface $points,
        private readonly EligibleAmount $eligibleAmount,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getEntityId() || !$order->getCustomerId() || $order->getCustomerIsGuest()) {
            return;
        }
        try {
            $this->points->accrue(
                (int)$order->getCustomerId(),
                (string)$order->getCustomerEmail(),
                (int)$order->getEntityId(),
                (string)$order->getIncrementId(),
                $this->eligibleAmount->forOrder($order),
                (int)$order->getStoreId()
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Loyalty accrual failed for order ' . $order->getIncrementId() . ': ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
