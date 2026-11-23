<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Api;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface;

/**
 * Append-only access to the points ledger. There is deliberately no update or delete (BR-05).
 *
 * @api
 */
interface PointsLedgerRepositoryInterface
{
    /**
     * Insert a new entry. Throws if the entry already has an id or the idempotency key exists.
     *
     * @param PointsLedgerEntryInterface $entry
     * @return PointsLedgerEntryInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function append(PointsLedgerEntryInterface $entry): PointsLedgerEntryInterface;

    /**
     * @param string $key
     * @return PointsLedgerEntryInterface|null
     */
    public function getByIdempotencyKey(string $key): ?PointsLedgerEntryInterface;

    /**
     * Newest first, keyset pagination: pass the last seen entry id as $beforeEntryId.
     *
     * @param int $customerId
     * @param int $limit
     * @param int|null $beforeEntryId
     * @return PointsLedgerEntryInterface[]
     */
    public function getListByCustomer(int $customerId, int $limit = 20, ?int $beforeEntryId = null): array;
}
