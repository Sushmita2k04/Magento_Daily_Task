<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Observer;

use Codilar\LoyaltyWallet\Model\Order\ReversalProcessor;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * After an order or credit memo has been committed, reverses points and restores wallet money as needed. It runs
 * after the commit and in its own transaction so a problem here can never block a cancellation or refund; the
 * sweeper repeats anything that failed. Registered for both the order and the credit memo save events.
 */
class ProcessOrderReversals implements ObserverInterface
{
    /**
     * @param ReversalProcessor $processor
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ReversalProcessor $processor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $creditmemo = $event->getCreditmemo();
        $order = $creditmemo ? $creditmemo->getOrder() : $event->getOrder();
        if (!$order instanceof Order || !$order->getEntityId()) {
            return;
        }
        if ($creditmemo === null && $order->getState() !== Order::STATE_CANCELED) {
            return; // an ordinary order save; only cancelled orders matter here (refunds arrive via the memo event)
        }
        try {
            $this->processor->process($order);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Loyalty reversal failed for order ' . $order->getIncrementId() . ': ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
