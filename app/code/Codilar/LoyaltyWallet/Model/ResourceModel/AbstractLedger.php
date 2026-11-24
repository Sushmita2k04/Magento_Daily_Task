<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Base resource model for the ledgers: rows can be inserted once and never changed or removed (BR-05).
 */
abstract class AbstractLedger extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _beforeSave(AbstractModel $object)
    {
        if ($object->getId() || $object->getOrigData() !== null) {
            throw new LocalizedException(__('Ledger entries are immutable; post a compensating entry instead.'));
        }
        return parent::_beforeSave($object);
    }

    /**
     * @inheritDoc
     */
    public function delete(AbstractModel $object)
    {
        throw new LocalizedException(__('Ledger entries cannot be deleted.'));
    }
}
