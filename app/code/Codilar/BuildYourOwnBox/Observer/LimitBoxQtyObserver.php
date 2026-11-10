<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Observer;

use Codilar\BuildYourOwnBox\Model\Product\Type\Box;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * A box is always a single unit: any attempt to change its quantity is reset to 1.
 */
class LimitBoxQtyObserver implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $item = $observer->getEvent()->getItem();
        if ($item && $item->getProductType() === Box::TYPE_CODE && (float)$item->getData('qty') !== 1.0) {
            $item->setData('qty', 1.0);
            $item->setMessage((string)__('Only one gift box is allowed per order.'));
        }
    }
}
