<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Wallet;

/**
 * A request to move wallet balance. The amount is always a positive magnitude; the service applies the sign that
 * belongs to the entry type.
 */
class WalletMovement
{
    /**
     * @param int $customerId
     * @param string $customerEmail
     * @param string $entryType One of the WalletLedgerEntryInterface::TYPE_* constants
     * @param string $amount Positive decimal string, at most 4 decimal places
     * @param string $reason
     * @param string $idempotencyKey Replaying the same key returns the original result and changes nothing
     * @param int|null $orderId
     * @param string|null $orderIncrementId
     * @param int|null $creditmemoId
     * @param string|null $operatorRef Required for manual entries
     */
    public function __construct(
        public readonly int $customerId,
        public readonly string $customerEmail,
        public readonly string $entryType,
        public readonly string $amount,
        public readonly string $reason,
        public readonly string $idempotencyKey,
        public readonly ?int $orderId = null,
        public readonly ?string $orderIncrementId = null,
        public readonly ?int $creditmemoId = null,
        public readonly ?string $operatorRef = null
    ) {
    }
}
