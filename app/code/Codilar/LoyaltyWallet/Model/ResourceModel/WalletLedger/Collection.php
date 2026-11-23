<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\ResourceModel\WalletLedger;

use Codilar\LoyaltyWallet\Model\WalletLedger as Model;
use Codilar\LoyaltyWallet\Model\ResourceModel\WalletLedger as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entry_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
