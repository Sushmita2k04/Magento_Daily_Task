<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Api\Data;

/**
 * One immutable movement of loyalty points (FR-09). Corrections are compensating entries (BR-05).
 *
 * @api
 */
interface PointsLedgerEntryInterface
{
    public const TYPE_ACCRUAL = 'ACCRUAL';
    public const TYPE_REVERSAL = 'REVERSAL';
    public const TYPE_CONVERSION = 'CONVERSION';
    public const TYPE_EXPIRY = 'EXPIRY';
    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    public const ENTRY_ID = 'entry_id';
    public const CUSTOMER_ID = 'customer_id';
    public const CUSTOMER_EMAIL = 'customer_email';
    public const ENTRY_TYPE = 'entry_type';
    public const POINTS = 'points';
    public const BALANCE_AFTER = 'balance_after';
    public const LOT_ID = 'lot_id';
    public const ORDER_ID = 'order_id';
    public const ORDER_INCREMENT_ID = 'order_increment_id';
    public const WALLET_ENTRY_ID = 'wallet_entry_id';
    public const WALLET_VALUE = 'wallet_value';
    public const REASON = 'reason';
    public const ACCRUED_AT = 'accrued_at';
    public const EXPIRES_AT = 'expires_at';
    public const IDEMPOTENCY_KEY = 'idempotency_key';
    public const CREATED_AT = 'created_at';

    /**
     * @return int|null
     */
    public function getEntryId(): ?int;

    /**
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * @return string
     */
    public function getCustomerEmail(): string;

    /**
     * @return string
     */
    public function getEntryType(): string;

    /**
     * Signed point quantity.
     *
     * @return int
     */
    public function getPoints(): int;

    /**
     * @return int
     */
    public function getBalanceAfter(): int;

    /**
     * @return int|null
     */
    public function getLotId(): ?int;

    /**
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * @return int|null
     */
    public function getWalletEntryId(): ?int;

    /**
     * Wallet value of a CONVERSION row at the rate used, as a decimal string; null otherwise.
     *
     * @return string|null
     */
    public function getWalletValue(): ?string;

    /**
     * @return string
     */
    public function getReason(): string;

    /**
     * @return string|null
     */
    public function getAccruedAt(): ?string;

    /**
     * @return string|null
     */
    public function getExpiresAt(): ?string;

    /**
     * @return string
     */
    public function getIdempotencyKey(): string;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;
}
