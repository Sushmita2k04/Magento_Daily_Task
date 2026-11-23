<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\ResourceModel;

class WalletLedger extends AbstractLedger
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('codilar_wallet_ledger', 'entry_id');
    }
}
