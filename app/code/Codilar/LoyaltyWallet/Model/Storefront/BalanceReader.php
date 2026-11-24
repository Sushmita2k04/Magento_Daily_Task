<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Storefront;

use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Points\AccrualService;
use Codilar\LoyaltyWallet\Model\Points\LotStore;
use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\App\ResourceConnection;

/**
 * Read-only view of one customer's balances for the storefront. It takes no locks. The ledger remains the
 * authority (BR-06); this only reads the stored balances and the lots.
 */
class BalanceReader
{
    /**
     * @param ResourceConnection $resource
     * @param LotStore $lots
     */
    public function __construct(private readonly ResourceConnection $resource, private readonly LotStore $lots)
    {
    }

    /**
     * @param int $customerId
     * @param DateTimeImmutable|null $at Defaults to now (UTC)
     * @return BalanceSummary
     */
    public function read(int $customerId, ?DateTimeImmutable $at = null): BalanceSummary
    {
        $now = ($at ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(AccrualService::DATE_FORMAT);
        $connection = $this->resource->getConnection();
        $points = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('codilar_loyalty_points_balance'), ['points_balance'])
                ->where('customer_id = ?', $customerId)
        );
        $wallet = (string)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('codilar_wallet_balance'), ['balance'])
                ->where('customer_id = ?', $customerId)
        );
        return new BalanceSummary(
            max(0, $points - $this->lots->pendingExpiryPoints($customerId, $now)),
            Decimal::normalize($wallet === '' ? '0' : $wallet),
            $this->lots->nextExpiry($customerId, $now)
        );
    }
}
