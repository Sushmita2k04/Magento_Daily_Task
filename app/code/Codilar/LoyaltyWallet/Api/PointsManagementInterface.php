<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Api;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface;
use Codilar\LoyaltyWallet\Model\Points\ConversionResult;

/**
 * Loyalty points operations. Every method that changes a balance is atomic and idempotent.
 *
 * @api
 */
interface PointsManagementInterface
{
    /**
     * Award points for an order (FR-01). Calling it again for the same order returns the original entry.
     *
     * @param int $customerId
     * @param string $customerEmail
     * @param int $orderId
     * @param string $orderIncrementId
     * @param string $eligibleAmount Order subtotal after discounts, excluding shipping and tax (decimal string)
     * @param int|null $storeId
     * @return PointsLedgerEntryInterface|null Null when the order earns no points
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function accrue(
        int $customerId,
        string $customerEmail,
        int $orderId,
        string $orderIncrementId,
        string $eligibleAmount,
        ?int $storeId = null
    ): ?PointsLedgerEntryInterface;

    /**
     * Convert points into wallet balance, oldest points first (FR-04, BR-01).
     *
     * @param int $customerId
     * @param string $customerEmail
     * @param int $points
     * @param string $requestKey Unique per user action; repeating it returns the first result,
     *                           it never converts twice
     * @return ConversionResult
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function convertToWallet(
        int $customerId,
        string $customerEmail,
        int $points,
        string $requestKey
    ): ConversionResult;

    /**
     * @param int $customerId
     * @return int
     */
    public function getBalance(int $customerId): int;

    /**
     * Date and quantity of the next scheduled expiry (FR-11).
     *
     * @param int $customerId
     * @return array{expires_at: string, points: int}|null
     */
    public function getNextExpiry(int $customerId): ?array;
}
