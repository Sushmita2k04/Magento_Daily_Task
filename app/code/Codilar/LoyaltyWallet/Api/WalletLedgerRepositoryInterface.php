<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Api;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface;

/**
 * Append-only access to the wallet ledger. There is deliberately no update or delete (BR-05).
 *
 * @api
 */
interface WalletLedgerRepositoryInterface
{
    /**
     * Insert a new entry. Throws if the entry already has an id or the idempotency key exists.
     *
     * @param WalletLedgerEntryInterface $entry
     * @return WalletLedgerEntryInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function append(WalletLedgerEntryInterface $entry): WalletLedgerEntryInterface;

    /**
     * @param string $key
     * @return WalletLedgerEntryInterface|null
     */
    public function getByIdempotencyKey(string $key): ?WalletLedgerEntryInterface;

    /**
     * Newest first, keyset pagination: pass the last seen entry id as $beforeEntryId.
     *
     * @param int $customerId
     * @param int $limit
     * @param int|null $beforeEntryId
     * @return WalletLedgerEntryInterface[]
     */
    public function getListByCustomer(int $customerId, int $limit = 20, ?int $beforeEntryId = null): array;
}
