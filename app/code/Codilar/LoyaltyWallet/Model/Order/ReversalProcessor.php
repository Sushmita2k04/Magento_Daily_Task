<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Order;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Locking\TransactionRunner;
use Codilar\LoyaltyWallet\Model\Points\EligibleAmount;
use Codilar\LoyaltyWallet\Model\Points\ReversalService;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

/**
 * Brings the loyalty side of an order in line with what has happened to it (FR-05, BR-03). It works out what should
 * be true from the order's current state, so it can be called after every save, from the sweeper, or by hand, and
 * only ever does the difference:
 *  - cancelled order: the wallet amount that has not been invoiced goes back to the wallet, and all earned points
 *    are reversed;
 *  - refunded credit memos: the earned points are reversed in proportion to the refunded share of the order.
 */
class ReversalProcessor
{
    /**
     * @param ResourceConnection $resource
     * @param TransactionRunner $transaction
     * @param WalletService $wallet
     * @param ReversalService $reversal
     * @param EligibleAmount $eligibleAmount
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly TransactionRunner $transaction,
        private readonly WalletService $wallet,
        private readonly ReversalService $reversal,
        private readonly EligibleAmount $eligibleAmount
    ) {
    }

    /**
     * @param OrderInterface $order
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process(OrderInterface $order): void
    {
        $customerId = (int)$order->getCustomerId();
        if ($customerId < 1 || !$order->getEntityId() || $order->getCustomerIsGuest()) {
            return;
        }
        $this->transaction->run(function () use ($order, $customerId): void {
            $cancelled = $order->getState() === Order::STATE_CANCELED;
            if ($cancelled) {
                $this->restoreWallet($order, $customerId);
            }
            $this->reversePoints($order, $customerId, $cancelled);
        });
    }

    /**
     * @param OrderInterface $order
     * @param int $customerId
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function restoreWallet(OrderInterface $order, int $customerId): void
    {
        $unpaid = bcsub(
            Decimal::fromFloat($order->getData('codilar_wallet_amount')),
            Decimal::fromFloat($order->getData('codilar_wallet_invoiced')),
            Decimal::SCALE
        );
        if (Decimal::sign($unpaid) <= 0) {
            return;
        }
        $this->wallet->credit(new WalletMovement(
            $customerId,
            (string)$order->getCustomerEmail(),
            W::TYPE_CREDIT_CANCELLATION,
            $unpaid,
            'Order #' . $order->getIncrementId() . ' was cancelled; wallet amount returned',
            'cancel:o' . $order->getEntityId(),
            (int)$order->getEntityId(),
            (string)$order->getIncrementId()
        ));
    }

    /**
     * @param OrderInterface $order
     * @param int $customerId
     * @param bool $cancelled
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function reversePoints(OrderInterface $order, int $customerId, bool $cancelled): void
    {
        $target = $cancelled ? null : $this->refundTarget($order);
        if ($target === 0) {
            return;
        }
        $this->reversal->reverseTo(
            $customerId,
            (int)$order->getEntityId(),
            (string)$order->getIncrementId(),
            (string)$order->getCustomerEmail(),
            $target
        );
    }

    /**
     * Cumulative points to have reversed for refunds so far: the points the order earned, times the share of the
     * earning base that has been refunded. Zero when nothing was refunded. Returns null (= everything) once the
     * whole base has been refunded.
     *
     * @param OrderInterface $order
     * @return int|null
     */
    private function refundTarget(OrderInterface $order): ?int
    {
        $connection = $this->resource->getConnection();
        $refunded = (string)$connection->fetchOne(
            $connection->select()
                ->from(
                    $this->resource->getTableName('sales_creditmemo'),
                    [new \Zend_Db_Expr('COALESCE(SUM(base_subtotal - ABS(COALESCE(base_discount_amount, 0))), 0)')]
                )
                ->where('order_id = ?', (int)$order->getEntityId())
                ->where('state = ?', \Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED)
        );
        $refunded = Decimal::normalize($refunded);
        $base = $this->eligibleAmount->forOrder($order);
        if (Decimal::sign($refunded) <= 0 || Decimal::sign($base) <= 0) {
            return 0;
        }
        if (bccomp($refunded, $base, Decimal::SCALE) >= 0) {
            return null;
        }
        $lot = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('codilar_loyalty_points_lot'), ['points_original'])
                ->where('order_id = ?', (int)$order->getEntityId())
        );
        return (int)bcdiv(bcmul((string)$lot, $refunded, 8), $base, 0);
    }
}
