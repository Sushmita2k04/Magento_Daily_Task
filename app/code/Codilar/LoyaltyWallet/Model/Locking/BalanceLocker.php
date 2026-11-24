<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Locking;

use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Serialises every balance change for one customer (NFR-01, NFR-03).
 *
 * Usage: open a DB transaction, call lock(), check idempotency, compute, append ledger rows, call set*Balance(),
 * commit. The row locks are released on commit or rollback. Locks are always taken wallet first, then points,
 * so two code paths can never deadlock on the pair.
 */
class BalanceLocker
{
    private const WALLET_TABLE = 'codilar_wallet_balance';
    private const POINTS_TABLE = 'codilar_loyalty_points_balance';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Lock the customer's wallet and points balance rows (creating them at zero if absent).
     *
     * @param int $customerId
     * @return array{wallet: string, points: int} Current balances as stored (wallet is a decimal string)
     * @throws LocalizedException When no transaction is open
     */
    public function lock(int $customerId): array
    {
        $connection = $this->connection();
        if ($connection->getTransactionLevel() < 1) {
            throw new LocalizedException(__('Balances must be locked inside a database transaction.'));
        }
        $wallet = $this->lockRow($connection, self::WALLET_TABLE, 'balance', $customerId);
        $points = $this->lockRow($connection, self::POINTS_TABLE, 'points_balance', $customerId);
        return ['wallet' => $wallet, 'points' => (int)$points];
    }

    /**
     * Persist the new wallet balance. A negative balance is refused (BR-02).
     *
     * @param int $customerId
     * @param string $balance Decimal string, scale 4
     * @return void
     * @throws LocalizedException
     */
    public function setWalletBalance(int $customerId, string $balance): void
    {
        if (!Decimal::isValid($balance)) {
            throw new LocalizedException(__('The wallet balance must be a decimal with at most 4 decimal places.'));
        }
        $balance = Decimal::normalize($balance);
        if (Decimal::sign($balance) < 0) {
            throw new LocalizedException(__('The wallet balance cannot fall below zero.'));
        }
        $this->update(self::WALLET_TABLE, ['balance' => $balance], $customerId);
    }

    /**
     * Persist the new points balance. A negative balance is refused.
     *
     * @param int $customerId
     * @param int $balance
     * @return void
     * @throws LocalizedException
     */
    public function setPointsBalance(int $customerId, int $balance): void
    {
        if ($balance < 0) {
            throw new LocalizedException(__('The points balance cannot fall below zero.'));
        }
        $this->update(self::POINTS_TABLE, ['points_balance' => $balance], $customerId);
    }

    /**
     * @return AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    /**
     * @param AdapterInterface $connection
     * @param string $table
     * @param string $column
     * @param int $customerId
     * @return string
     */
    private function lockRow(AdapterInterface $connection, string $table, string $column, int $customerId): string
    {
        $tableName = $this->resource->getTableName($table);
        $connection->insertOnDuplicate($tableName, ['customer_id' => $customerId], ['customer_id']);
        $select = $connection->select()
            ->from($tableName, [$column])
            ->where('customer_id = ?', $customerId)
            ->forUpdate(true);
        return Decimal::normalize((string)$connection->fetchOne($select));
    }

    /**
     * @param string $table
     * @param array $data
     * @param int $customerId
     * @return void
     */
    private function update(string $table, array $data, int $customerId): void
    {
        $this->connection()->update(
            $this->resource->getTableName($table),
            $data,
            ['customer_id = ?' => $customerId]
        );
    }
}
