<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Locking;

use Magento\Framework\App\ResourceConnection;

/**
 * Runs work in one database transaction. If a transaction is already open (for example checkout's), the work joins
 * it and the outer code decides whether to commit or roll back.
 */
class TransactionRunner
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param callable $work
     * @return mixed Whatever $work returns
     * @throws \Throwable Rethrown after rollback when this call opened the transaction
     */
    public function run(callable $work): mixed
    {
        $connection = $this->resource->getConnection();
        if ($connection->getTransactionLevel() > 0) {
            return $work();
        }
        $connection->beginTransaction();
        try {
            $result = $work();
            $connection->commit();
            return $result;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
