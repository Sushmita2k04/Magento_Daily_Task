<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\ResourceModel;

class PointsLedger extends AbstractLedger
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('codilar_loyalty_points_ledger', 'entry_id');
    }
}
