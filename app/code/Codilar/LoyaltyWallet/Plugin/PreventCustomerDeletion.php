<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Plugin;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * Ledgers are kept indefinitely (BR-05, constraint "retained"), and a wallet balance is money the shop owes. A
 * customer with any points or wallet history, or a balance, cannot be deleted.
 */
class PreventCustomerDeletion
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param CustomerRepositoryInterface $subject
     * @param CustomerInterface $customer
     * @return null
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDelete(CustomerRepositoryInterface $subject, CustomerInterface $customer)
    {
        $this->assertDeletable((int)$customer->getId());
        return null;
    }

    /**
     * @param CustomerRepositoryInterface $subject
     * @param int|string $customerId
     * @return null
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDeleteById(CustomerRepositoryInterface $subject, $customerId)
    {
        $this->assertDeletable((int)$customerId);
        return null;
    }

    /**
     * @param int $customerId
     * @return void
     * @throws LocalizedException
     */
    private function assertDeletable(int $customerId): void
    {
        if ($customerId < 1) {
            return;
        }
        $connection = $this->resource->getConnection();
        foreach (['codilar_loyalty_points_ledger', 'codilar_wallet_ledger'] as $table) {
            $found = $connection->fetchOne(
                $connection->select()
                    ->from($this->resource->getTableName($table), ['entry_id'])
                    ->where('customer_id = ?', $customerId)
                    ->limit(1)
            );
            if ($found) {
                throw new LocalizedException(__(
                    'This customer has loyalty points or wallet history, which must be kept, '
                    . 'so the account cannot be deleted.'
                ));
            }
        }
    }
}
