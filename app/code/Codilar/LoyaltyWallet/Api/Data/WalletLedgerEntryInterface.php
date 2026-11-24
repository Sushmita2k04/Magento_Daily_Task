<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Api\Data;

/**
 * One immutable movement of store wallet balance (FR-09). All monetary values are scale-4 decimal strings.
 *
 * @api
 */
interface WalletLedgerEntryInterface
{
    public const TYPE_CREDIT_CONVERSION = 'CREDIT_CONVERSION';
    public const TYPE_CREDIT_REFUND = 'CREDIT_REFUND';
    public const TYPE_CREDIT_CANCELLATION = 'CREDIT_CANCELLATION';
    public const TYPE_CREDIT_MANUAL = 'CREDIT_MANUAL';
    public const TYPE_DEBIT_REDEMPTION = 'DEBIT_REDEMPTION';
    public const TYPE_DEBIT_MANUAL = 'DEBIT_MANUAL';
    public const TYPE_CLAWBACK = 'CLAWBACK';
    public const TYPE_WRITE_OFF = 'WRITE_OFF';
    public const TYPE_REDEMPTION_RELEASE = 'REDEMPTION_RELEASE';

    public const ENTRY_ID = 'entry_id';
    public const CUSTOMER_ID = 'customer_id';
    public const CUSTOMER_EMAIL = 'customer_email';
    public const ENTRY_TYPE = 'entry_type';
    public const AMOUNT = 'amount';
    public const MEMO_AMOUNT = 'memo_amount';
    public const BALANCE_AFTER = 'balance_after';
    public const REASON = 'reason';
    public const ORDER_ID = 'order_id';
    public const ORDER_INCREMENT_ID = 'order_increment_id';
    public const CREDITMEMO_ID = 'creditmemo_id';
    public const OPERATOR_REF = 'operator_ref';
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
     * Signed amount as a decimal string, e.g. "-12.5000". Returns "" when unset so validation can report it.
     *
     * @return string
     */
    public function getAmount(): string;

    /**
     * Written-off residual on WRITE_OFF rows; null otherwise.
     *
     * @return string|null
     */
    public function getMemoAmount(): ?string;

    /**
     * Resulting balance as a decimal string. Returns "" when unset so validation can report it.
     *
     * @return string
     */
    public function getBalanceAfter(): string;

    /**
     * @return string
     */
    public function getReason(): string;

    /**
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * @return int|null
     */
    public function getCreditmemoId(): ?int;

    /**
     * @return string|null
     */
    public function getOperatorRef(): ?string;

    /**
     * @return string
     */
    public function getIdempotencyKey(): string;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;
}
