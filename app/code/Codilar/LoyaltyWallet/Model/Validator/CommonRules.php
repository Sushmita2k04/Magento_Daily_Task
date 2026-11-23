<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Validator;

use Magento\Framework\Exception\InputException;

/**
 * Rules shared by both ledgers.
 */
class CommonRules
{
    /**
     * @param int $customerId
     * @param string $email
     * @param string $reason
     * @param string $idempotencyKey
     * @return string[] Error messages
     */
    public static function check(int $customerId, string $email, string $reason, string $idempotencyKey): array
    {
        $errors = [];
        $customerId <= 0 && $errors[] = 'A customer id is required.';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255) {
            $errors[] = 'A valid customer email (at most 255 characters) is required.';
        }
        if (trim($reason) === '' || strlen($reason) > 255) {
            $errors[] = 'A reason of 1 to 255 characters is required.';
        }
        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 128) {
            $errors[] = 'An idempotency key of 1 to 128 characters is required.';
        }
        return $errors;
    }

    /**
     * @param string[] $errors
     * @return void
     * @throws InputException
     */
    public static function throwIfAny(array $errors): void
    {
        if ($errors === []) {
            return;
        }
        if (count($errors) === 1) {
            throw new InputException(__($errors[0]));
        }
        // Magento's InputException exposes getErrors() only once a second error has been added.
        $exception = new InputException(__('The ledger entry is invalid.'));
        foreach ($errors as $error) {
            $exception->addError(__($error));
        }
        throw $exception;
    }
}
