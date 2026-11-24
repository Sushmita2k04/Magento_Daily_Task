<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface;
use Codilar\LoyaltyWallet\Api\PointsLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Model\ResourceModel\PointsLedger as ResourceModel;
use Codilar\LoyaltyWallet\Model\ResourceModel\PointsLedger\CollectionFactory;
use Codilar\LoyaltyWallet\Model\Validator\PointsLedgerEntryValidator;
use Magento\Framework\Exception\LocalizedException;

/**
 * Append-only repository. Callers check the idempotency key under the customer balance lock
 * (see BalanceLocker) before appending; the unique index is the final backstop.
 */
class PointsLedgerRepository implements PointsLedgerRepositoryInterface
{
    /**
     * @param ResourceModel $resource
     * @param CollectionFactory $collectionFactory
     * @param PointsLedgerEntryValidator $validator
     */
    public function __construct(
        private readonly ResourceModel $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly PointsLedgerEntryValidator $validator
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws \Magento\Framework\Exception\InputException When the entry fails validation
     */
    public function append(PointsLedgerEntryInterface $entry): PointsLedgerEntryInterface
    {
        if (!$entry instanceof PointsLedger) {
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
    public function getByIdempotencyKey(string $key): ?PointsLedgerEntryInterface
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
