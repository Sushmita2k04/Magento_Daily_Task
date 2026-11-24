<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Block\Order;

use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;

/**
 * Adds the "Store Wallet" line to the totals of an order, invoice or credit memo page or email (NFR-04). The same
 * block serves all three: each source carries its own codilar_wallet_amount.
 */
class WalletTotal extends Template
{
    /**
     * Called by the parent totals block.
     *
     * @return $this
     */
    public function initTotals(): self
    {
        $parent = $this->getParentBlock();
        $source = $parent ? $parent->getSource() : null;
        if (!$source) {
            return $this;
        }
        $amount = Decimal::fromFloat($source->getData('codilar_wallet_amount'));
        if (Decimal::sign($amount) > 0) {
            $parent->addTotalBefore(new DataObject([
                'code' => 'codilar_wallet',
                'label' => __('Store Wallet'),
                'value' => -(float)$amount,
                'base_value' => -(float)$amount,
            ]), 'grand_total');
        }
        return $this;
    }
}
