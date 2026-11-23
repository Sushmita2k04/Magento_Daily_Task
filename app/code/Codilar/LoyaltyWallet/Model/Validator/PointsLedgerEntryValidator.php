<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Validator;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface as E;
use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Framework\Exception\InputException;

/**
 * Rejects points ledger entries that are incomplete or internally inconsistent, before anything is written.
 */
class PointsLedgerEntryValidator
{
    private const LOT_TYPES = [E::TYPE_ACCRUAL, E::TYPE_REVERSAL, E::TYPE_CONVERSION, E::TYPE_EXPIRY];
    private const NEGATIVE_TYPES = [E::TYPE_REVERSAL, E::TYPE_CONVERSION, E::TYPE_EXPIRY];

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
        if (!in_array($type, array_merge(self::LOT_TYPES, [E::TYPE_ADJUSTMENT]), true)) {
            $errors[] = 'Unknown points entry type.';
        }
        $points = $entry->getPoints();
        if ($points === 0) {
            $errors[] = 'Points must not be zero.';
        } elseif ($type === E::TYPE_ACCRUAL && $points < 0) {
            $errors[] = 'An accrual must be positive.';
        } elseif (in_array($type, self::NEGATIVE_TYPES, true) && $points > 0) {
            $errors[] = 'This entry type must be negative.';
        }
        $entry->getBalanceAfter() < 0 && $errors[] = 'Resulting balance must not be negative.';

        if (in_array($type, self::LOT_TYPES, true) && ($entry->getLotId() ?? 0) <= 0) {
            $errors[] = 'This entry type must reference a points lot.';
        }
        if ($type === E::TYPE_ACCRUAL) {
            $accrued = strtotime((string)$entry->getAccruedAt());
            $expires = strtotime((string)$entry->getExpiresAt());
            if ($accrued === false || $expires === false || $expires <= $accrued) {
                $errors[] = 'An accrual needs an accrual date and a later expiry date.';
            }
        }
        if ($type === E::TYPE_CONVERSION) {
            $value = $entry->getWalletValue();
            if ($value === null || !Decimal::isValid($value) || Decimal::sign($value) <= 0) {
                $errors[] = 'A conversion must record a positive wallet value.';
            }
            ($entry->getWalletEntryId() ?? 0) <= 0 && $errors[] = 'A conversion must reference its wallet credit.';
        }
        CommonRules::throwIfAny($errors);
    }
}
