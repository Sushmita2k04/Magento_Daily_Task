<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Sweeper;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\Model\Order\ReversalProcessor;
use Codilar\LoyaltyWallet\Model\Points\EligibleAmount;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Safety net for the two steps that happen outside the order's own database transaction:
 *  - wallet debits whose order was never saved (a crash between debit and save) are released;
 *  - orders that were placed but never earned their points (accrual failed) are accrued.
 * Both are idempotent, so running it repeatedly is safe.
 */
class Sweeper
{
    /**
     * @param ResourceConnection $resource
     * @param WalletService $wallet
     * @param PointsManagementInterface $points
     * @param EligibleAmount $eligibleAmount
     * @param ReversalProcessor $reversals
     * @param OrderRepositoryInterface $orders
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly WalletService $wallet,
        private readonly PointsManagementInterface $points,
        private readonly EligibleAmount $eligibleAmount,
        private readonly ReversalProcessor $reversals,
        private readonly OrderRepositoryInterface $orders,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int $minutesOld Only debits older than this are considered stuck
     * @param int $days How far back to look for orders that missed their points
     * @return array{released: int, accrued: int, reversed: int, failed: int}
     */
    public function run(int $minutesOld = 60, int $days = 30): array
    {
        $report = ['released' => 0, 'accrued' => 0, 'reversed' => 0, 'failed' => 0];
        foreach ($this->stuckRedemptions($minutesOld) as $row) {
            try {
                $this->wallet->credit(new WalletMovement(
                    (int)$row['customer_id'],
                    (string)$row['customer_email'],
                    W::TYPE_REDEMPTION_RELEASE,
                    ltrim((string)$row['amount'], '-'),
                    'Order #' . $row['order_increment_id'] . ' was never placed; wallet amount returned',
                    'release:' . $row['idempotency_key'],
                    null,
                    (string)$row['order_increment_id']
                ));
                $report['released']++;
            } catch (\Throwable $e) {
                $report['failed']++;
                $this->logger->error('Stuck redemption release failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
        foreach ($this->ordersMissingPoints($days) as $orderId) {
            try {
                $order = $this->orders->get((int)$orderId);
                $entry = $this->points->accrue(
                    (int)$order->getCustomerId(),
                    (string)$order->getCustomerEmail(),
                    (int)$order->getEntityId(),
                    (string)$order->getIncrementId(),
                    $this->eligibleAmount->forOrder($order),
                    (int)$order->getStoreId()
                );
                $entry !== null && $report['accrued']++;
            } catch (\Throwable $e) {
                $report['failed']++;
                $this->logger->error('Missed accrual retry failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
        foreach ($this->ordersNeedingReversal($days * 3) as $orderId) {
            try {
                $this->reversals->process($this->orders->get((int)$orderId));
                $report['reversed']++;
            } catch (\Throwable $e) {
                $report['failed']++;
                $this->logger->error('Reversal retry failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
        return $report;
    }

    /**
     * Cancelled or refunded orders of registered customers whose points reversal is not finished, or whose
     * cancellation did not return the wallet amount. A candidate that turns out to need nothing is a cheap no-op.
     *
     * @param int $days
     * @return array<int, int|string>
     */
    private function ordersNeedingReversal(int $days): array
    {
        $connection = $this->resource->getConnection();
        $since = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . max(1, $days) . ' days')->format('Y-m-d H:i:s');
        $order = $this->resource->getTableName('sales_order');
        $lot = $this->resource->getTableName('codilar_loyalty_points_lot');
        $ledger = $this->resource->getTableName('codilar_wallet_ledger');
        $memo = $this->resource->getTableName('sales_creditmemo');
        return $connection->fetchCol(
            "SELECT o.entity_id FROM $order o
             WHERE o.customer_id IS NOT NULL AND o.customer_is_guest = 0 AND o.updated_at >= :since
               AND (
                 (o.state = 'canceled' AND (
                    EXISTS (SELECT 1 FROM $lot l WHERE l.order_id = o.entity_id
                            AND l.points_reversal_handled < l.points_original)
                    OR (o.codilar_wallet_amount > o.codilar_wallet_invoiced
                        AND NOT EXISTS (SELECT 1 FROM $ledger w
                                        WHERE w.idempotency_key = CONCAT('cancel:o', o.entity_id)))))
                 OR (o.state <> 'canceled' AND EXISTS (SELECT 1 FROM $memo m WHERE m.order_id = o.entity_id AND m.state = 2)
                     AND EXISTS (SELECT 1 FROM $lot l WHERE l.order_id = o.entity_id
                                 AND l.points_reversal_handled < l.points_original))
               )
             ORDER BY o.entity_id LIMIT 200",
            ['since' => $since]
        );
    }

    /**
     * @param int $minutesOld
     * @return array
     */
    private function stuckRedemptions(int $minutesOld): array
    {
        $connection = $this->resource->getConnection();
        $ledger = $this->resource->getTableName('codilar_wallet_ledger');
        $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . max(1, $minutesOld) . ' minutes')->format('Y-m-d H:i:s');
        return $connection->fetchAll(
            "SELECT d.customer_id, d.customer_email, d.amount, d.order_increment_id, d.idempotency_key
             FROM $ledger d
             WHERE d.entry_type = 'DEBIT_REDEMPTION' AND d.created_at <= :cutoff
               AND d.order_increment_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM $ledger r WHERE r.idempotency_key = CONCAT('release:', d.idempotency_key))
               AND NOT EXISTS (SELECT 1 FROM " . $this->resource->getTableName('sales_order') . " o
                               WHERE o.increment_id = d.order_increment_id)",
            ['cutoff' => $cutoff]
        );
    }

    /**
     * @param int $days
     * @return array<int, int|string>
     */
    private function ordersMissingPoints(int $days): array
    {
        $connection = $this->resource->getConnection();
        $since = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . max(1, $days) . ' days')->format('Y-m-d H:i:s');
        return $connection->fetchCol(
            'SELECT o.entity_id FROM ' . $this->resource->getTableName('sales_order') . ' o
             WHERE o.customer_id IS NOT NULL AND o.customer_is_guest = 0 AND o.state <> \'canceled\'
               AND o.created_at >= :since
               AND NOT EXISTS (SELECT 1 FROM ' . $this->resource->getTableName('codilar_loyalty_points_lot') . ' l
                               WHERE l.order_id = o.entity_id)
             ORDER BY o.entity_id LIMIT 500',
            ['since' => $since]
        );
    }
}
