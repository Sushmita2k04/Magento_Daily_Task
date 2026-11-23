<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Points;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Data access for points lots (accrual batches). Lots are an allocation projection used for FIFO consumption and
 * expiry; every change to them is mirrored by a points ledger entry, and the ledger stays authoritative (BR-06).
 * All write methods must be called inside a transaction that holds the customer's BalanceLocker lock.
 */
class LotStore
{
    private const LOT = 'codilar_loyalty_points_lot';
    private const LEDGER = 'codilar_loyalty_points_ledger';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param int $customerId
     * @param int|null $orderId
     * @param int $points
     * @param string $accruedAt UTC 'Y-m-d H:i:s'
     * @param string $expiresAt UTC 'Y-m-d H:i:s'
     * @return int New lot id
     */
    public function create(int $customerId, ?int $orderId, int $points, string $accruedAt, string $expiresAt): int
    {
        $connection = $this->connection();
        $connection->insert($this->table(self::LOT), [
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'points_original' => $points,
            'points_remaining' => $points,
            'accrued_at' => $accruedAt,
            'expires_at' => $expiresAt,
        ]);
        return (int)$connection->lastInsertId($this->table(self::LOT));
    }

    /**
     * Lots the customer can spend now, oldest expiry first (FIFO), row-locked.
     *
     * @param int $customerId
     * @param string $now UTC 'Y-m-d H:i:s'
     * @return array<int, array{lot_id: int, points_remaining: int, accrued_at: string, expires_at: string}>
     */
    public function lockSpendable(int $customerId, string $now): array
    {
        $select = $this->connection()->select()
            ->from($this->table(self::LOT), ['lot_id', 'points_remaining', 'accrued_at', 'expires_at'])
            ->where('customer_id = ?', $customerId)
            ->where('expired_at IS NULL')
            ->where('points_remaining > 0')
            ->where('expires_at > ?', $now)
            ->order(['expires_at ASC', 'lot_id ASC'])
            ->forUpdate(true);
        return array_map(static fn (array $row) => [
            'lot_id' => (int)$row['lot_id'],
            'points_remaining' => (int)$row['points_remaining'],
            'accrued_at' => (string)$row['accrued_at'],
            'expires_at' => (string)$row['expires_at'],
        ], $this->connection()->fetchAll($select));
    }

    /**
     * Take points from a lot. Refuses to go below zero.
     *
     * @param int $lotId
     * @param int $points
     * @return void
     * @throws LocalizedException
     */
    public function consume(int $lotId, int $points): void
    {
        $affected = $this->connection()->update(
            $this->table(self::LOT),
            ['points_remaining' => new \Zend_Db_Expr('points_remaining - ' . $points)],
            ['lot_id = ?' => $lotId, 'points_remaining >= ?' => $points]
        );
        if ($affected !== 1) {
            throw new LocalizedException(__('Points lot %1 cannot cover %2 points.', $lotId, $points));
        }
    }

    /**
     * Ids of lots whose expiry has passed and that have not been expired yet (index: expires_at, expired_at).
     *
     * @param string $now UTC 'Y-m-d H:i:s'
     * @param int $limit
     * @param int[] $excludeLotIds
     * @return array<int, array{lot_id: int, customer_id: int}>
     */
    public function findDue(string $now, int $limit, array $excludeLotIds = []): array
    {
        $select = $this->connection()->select()
            ->from($this->table(self::LOT), ['lot_id', 'customer_id'])
            ->where('expires_at <= ?', $now)
            ->where('expired_at IS NULL')
            ->order(['expires_at ASC', 'lot_id ASC'])
            ->limit($limit);
        if ($excludeLotIds) {
            $select->where('lot_id NOT IN (?)', $excludeLotIds);
        }
        return array_map(static fn (array $row) => [
            'lot_id' => (int)$row['lot_id'],
            'customer_id' => (int)$row['customer_id'],
        ], $this->connection()->fetchAll($select));
    }

    /**
     * What an expiry run would process right now (for --dry-run).
     *
     * @param string $now UTC 'Y-m-d H:i:s'
     * @return array{lots: int, points: int}
     */
    public function summarizeDue(string $now): array
    {
        $select = $this->connection()->select()
            ->from($this->table(self::LOT), [
                'lots' => new \Zend_Db_Expr('COUNT(*)'),
                'points' => new \Zend_Db_Expr('COALESCE(SUM(points_remaining), 0)'),
            ])
            ->where('expires_at <= ?', $now)
            ->where('expired_at IS NULL');
        $row = $this->connection()->fetchRow($select);
        return ['lots' => (int)$row['lots'], 'points' => (int)$row['points']];
    }

    /**
     * Points already past their expiry time that the daily run has not closed yet. They are still in the stored
     * balance but can no longer be spent, so storefront displays subtract them.
     *
     * @param int $customerId
     * @param string $now UTC 'Y-m-d H:i:s'
     * @return int
     */
    public function pendingExpiryPoints(int $customerId, string $now): int
    {
        $select = $this->connection()->select()
            ->from($this->table(self::LOT), [new \Zend_Db_Expr('COALESCE(SUM(points_remaining), 0)')])
            ->where('customer_id = ?', $customerId)
            ->where('expired_at IS NULL')
            ->where('expires_at <= ?', $now);
        return (int)$this->connection()->fetchOne($select);
    }

    /**
     * Locks a lot only if it has not been expired yet. Null means another run already expired it.
     *
     * @param int $lotId
     * @return array{points_remaining: int}|null
     */
    public function lockUnexpired(int $lotId): ?array
    {
        $select = $this->connection()->select()
            ->from($this->table(self::LOT), ['points_remaining'])
            ->where('lot_id = ?', $lotId)
            ->where('expired_at IS NULL')
            ->forUpdate(true);
        $row = $this->connection()->fetchRow($select);
        return $row ? ['points_remaining' => (int)$row['points_remaining']] : null;
    }

    /**
     * @param int $lotId
     * @param string $now UTC 'Y-m-d H:i:s'
     * @return void
     */
    public function markExpired(int $lotId, string $now): void
    {
        $this->connection()->update(
            $this->table(self::LOT),
            ['points_remaining' => 0, 'expired_at' => $now],
            ['lot_id = ?' => $lotId]
        );
    }

    /**
     * The earliest expiry day with points still to expire (FR-11): its first timestamp and the points due that day.
     *
     * @param int $customerId
     * @param string $now UTC 'Y-m-d H:i:s'
     * @return array{expires_at: string, points: int}|null
     */
    public function nextExpiry(int $customerId, string $now): ?array
    {
        $select = $this->connection()->select()
            ->from($this->table(self::LOT), [
                'expires_at' => new \Zend_Db_Expr('MIN(expires_at)'),
                'points' => new \Zend_Db_Expr('SUM(points_remaining)'),
            ])
            ->where('customer_id = ?', $customerId)
            ->where('expired_at IS NULL')
            ->where('points_remaining > 0')
            ->where('expires_at > ?', $now)
            ->group(new \Zend_Db_Expr('DATE(expires_at)'))
            ->order(new \Zend_Db_Expr('DATE(expires_at) ASC'))
            ->limit(1);
        $row = $this->connection()->fetchRow($select);
        return $row ? ['expires_at' => (string)$row['expires_at'], 'points' => (int)$row['points']] : null;
    }

    /**
     * The lot an order earned, row-locked, with its reversal bookkeeping. Null when the order earned no points.
     *
     * @param int $orderId
     * @return array{lot_id: int, customer_id: int, points_original: int, points_remaining: int,
     *     points_reversal_handled: int, points_clawed: int}|null
     */
    public function lockByOrder(int $orderId): ?array
    {
        $select = $this->connection()->select()
            ->from($this->table(self::LOT), [
                'lot_id', 'customer_id', 'points_original', 'points_remaining',
                'points_reversal_handled', 'points_clawed',
            ])
            ->where('order_id = ?', $orderId)
            ->forUpdate(true);
        $row = $this->connection()->fetchRow($select);
        return $row ? array_map('intval', $row) : null;
    }

    /**
     * Points of a lot that were converted to wallet balance, and the wallet value they were credited.
     *
     * @param int $lotId
     * @return array{points: int, value: string}
     */
    public function convertedTotals(int $lotId): array
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->table(self::LEDGER), [
                    'points' => new \Zend_Db_Expr('COALESCE(-SUM(points), 0)'),
                    'value' => new \Zend_Db_Expr('COALESCE(SUM(wallet_value), 0)'),
                ])
                ->where('lot_id = ?', $lotId)
                ->where('entry_type = ?', 'CONVERSION')
        );
        return ['points' => (int)$row['points'], 'value' => (string)$row['value']];
    }

    /**
     * Records that reversal has been processed up to $handled points, and that $clawedMore converted points were
     * clawed back.
     *
     * @param int $lotId
     * @param int $handled New cumulative handled value
     * @param int $clawedMore
     * @return void
     */
    public function markReversal(int $lotId, int $handled, int $clawedMore): void
    {
        $this->connection()->update(
            $this->table(self::LOT),
            [
                'points_reversal_handled' => $handled,
                'points_clawed' => new \Zend_Db_Expr('points_clawed + ' . max(0, $clawedMore)),
            ],
            ['lot_id = ?' => $lotId]
        );
    }

    /**
     * Email recorded on the lot's first ledger entry; survives deletion of the customer account.
     *
     * @param int $lotId
     * @return string
     */
    public function snapshotEmail(int $lotId): string
    {
        $select = $this->connection()->select()
            ->from($this->table(self::LEDGER), ['customer_email'])
            ->where('lot_id = ?', $lotId)
            ->order('entry_id ASC')
            ->limit(1);
        return (string)$this->connection()->fetchOne($select);
    }

    /**
     * @return AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    /**
     * @param string $name
     * @return string
     */
    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
