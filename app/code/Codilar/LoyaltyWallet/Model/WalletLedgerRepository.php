<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface;
use Codilar\LoyaltyWallet\Api\WalletLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Model\ResourceModel\WalletLedger as ResourceModel;
use Codilar\LoyaltyWallet\Model\ResourceModel\WalletLedger\CollectionFactory;
use Codilar\LoyaltyWallet\Model\Validator\WalletLedgerEntryValidator;
use Magento\Framework\Exception\LocalizedException;

/**
 * Append-only repository. Callers check the idempotency key under the customer balance lock
 * (see BalanceLocker) before appending; the unique index is the final backstop.
 */
class WalletLedgerRepository implements WalletLedgerRepositoryInterface
{
    /**
     * @param ResourceModel $resource
     * @param CollectionFactory $collectionFactory
     * @param WalletLedgerEntryValidator $validator
     */
    public function __construct(
        private readonly ResourceModel $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly WalletLedgerEntryValidator $validator
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws \Magento\Framework\Exception\InputException When the entry fails validation
     */
    public function append(WalletLedgerEntryInterface $entry): WalletLedgerEntryInterface
    {
        if (!$entry instanceof WalletLedger) {
            throw new LocalizedException(__('Unsupported ledger entry implementation.'));
        }
        $this->validator->validate($entry);
        // Models built from constructor data are not flagged as changed, and AbstractDb::save() would skip them.
        $entry->setHasDataChanges(true);
        $this->resource->save($entry);
        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function getByIdempotencyKey(string $key): ?WalletLedgerEntryInterface
    {
        $item = $this->collectionFactory->create()
            ->addFieldToFilter('idempotency_key', $key)
            ->setPageSize(1)
            ->getFirstItem();
        return $item->getId() ? $item : null;
    }

    /**
     * @inheritDoc
     */
    public function getListByCustomer(int $customerId, int $limit = 20, ?int $beforeEntryId = null): array
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('customer_id', $customerId)
            ->setOrder('entry_id', 'DESC')
            ->setPageSize(max(1, min(200, $limit)));
        if ($beforeEntryId !== null) {
            $collection->addFieldToFilter('entry_id', ['lt' => $beforeEntryId]);
        }
        return array_values($collection->getItems());
    }
}
