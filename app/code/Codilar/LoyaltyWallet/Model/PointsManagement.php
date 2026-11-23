<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface;
use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\Model\Points\AccrualService;
use Codilar\LoyaltyWallet\Model\Points\ConversionResult;
use Codilar\LoyaltyWallet\Model\Points\ConversionService;
use Codilar\LoyaltyWallet\Model\Points\LotStore;
use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\App\ResourceConnection;

/**
 * Entry point for points operations; the work lives in the Points services.
 */
class PointsManagement implements PointsManagementInterface
{
    /**
     * @param AccrualService $accrual
     * @param ConversionService $conversion
     * @param LotStore $lots
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly AccrualService $accrual,
        private readonly ConversionService $conversion,
        private readonly LotStore $lots,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritDoc
     */
    public function accrue(
        int $customerId,
        string $customerEmail,
        int $orderId,
        string $orderIncrementId,
        string $eligibleAmount,
        ?int $storeId = null
    ): ?PointsLedgerEntryInterface {
        return $this->accrual->accrue(
            $customerId,
            $customerEmail,
            $orderId,
            $orderIncrementId,
            $eligibleAmount,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function convertToWallet(
        int $customerId,
        string $customerEmail,
        int $points,
        string $requestKey
    ): ConversionResult {
        return $this->conversion->convert($customerId, $customerEmail, $points, $requestKey);
    }

    /**
     * @inheritDoc
     */
    public function getBalance(int $customerId): int
    {
        $connection = $this->resource->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('codilar_loyalty_points_balance'), ['points_balance'])
                ->where('customer_id = ?', $customerId)
        );
    }

    /**
     * @inheritDoc
     */
    public function getNextExpiry(int $customerId): ?array
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(AccrualService::DATE_FORMAT);
        return $this->lots->nextExpiry($customerId, $now);
    }
}
