<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Validator;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as E;
use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Framework\Exception\InputException;

/**
 * Rejects wallet ledger entries that are incomplete or internally inconsistent, before anything is written.
 */
class WalletLedgerEntryValidator
{
    private const POSITIVE_TYPES = [
        E::TYPE_CREDIT_CONVERSION, E::TYPE_CREDIT_REFUND, E::TYPE_CREDIT_CANCELLATION,
        E::TYPE_CREDIT_MANUAL, E::TYPE_REDEMPTION_RELEASE,
    ];
    private const NEGATIVE_TYPES = [E::TYPE_DEBIT_REDEMPTION, E::TYPE_DEBIT_MANUAL, E::TYPE_CLAWBACK];
    private const OPERATOR_TYPES = [E::TYPE_CREDIT_MANUAL, E::TYPE_DEBIT_MANUAL];

    /**
     * @param E $entry
     * @return void
     * @throws InputException With every problem found
     */
    public function validate(E $entry): void
    {
        $errors = CommonRules::check(
            $entry->getCustomerId(),
            $entry->getCustomerEmail(),
            $entry->getReason(),
            $entry->getIdempotencyKey()
        );
        $type = $entry->getEntryType();
        $known = array_merge(self::POSITIVE_TYPES, self::NEGATIVE_TYPES, [E::TYPE_WRITE_OFF]);
        if (!in_array($type, $known, true)) {
            $errors[] = 'Unknown wallet entry type.';
        }

        $amount = $entry->getAmount();
        if (!Decimal::isValid($amount)) {
            $errors[] = 'Amount must be a decimal with at most 4 decimal places.';
        } elseif ($type === E::TYPE_WRITE_OFF) {
            Decimal::sign($amount) !== 0 && $errors[] = 'A write-off must not move the balance (amount must be 0).';
            $memo = $entry->getMemoAmount();
            if ($memo === null || !Decimal::isValid($memo) || Decimal::sign($memo) <= 0) {
                $errors[] = 'A write-off must record a positive written-off amount.';
            }
        } else {
            $sign = Decimal::sign($amount);
            if ($sign === 0) {
                $errors[] = 'Amount must not be zero.';
            } elseif (in_array($type, self::POSITIVE_TYPES, true) && $sign < 0) {
                $errors[] = 'This entry type must have a positive amount.';
            } elseif (in_array($type, self::NEGATIVE_TYPES, true) && $sign > 0) {
                $errors[] = 'This entry type must have a negative amount.';
            }
            $entry->getMemoAmount() !== null && $errors[] = 'Only a write-off may carry a written-off amount.';
        }

        $balance = $entry->getBalanceAfter();
        if (!Decimal::isValid($balance) || Decimal::sign($balance) < 0) {
            $errors[] = 'Resulting balance must be a non-negative decimal.';
        }
        if (in_array($type, self::OPERATOR_TYPES, true)
            && (trim((string)$entry->getOperatorRef()) === '' || strlen((string)$entry->getOperatorRef()) > 64)
        ) {
            $errors[] = 'Manual entries require an operator reference of at most 64 characters.';
        }
        foreach (['order id' => $entry->getOrderId(), 'credit memo id' => $entry->getCreditmemoId()] as $label => $id) {
            $id !== null && $id <= 0 && $errors[] = sprintf('The %s must be positive when given.', $label);
        }
        CommonRules::throwIfAny($errors);
    }
}
