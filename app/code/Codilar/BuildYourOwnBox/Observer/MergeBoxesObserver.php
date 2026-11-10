<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Observer;

use Codilar\BuildYourOwnBox\Model\QuoteGuard;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Guest-to-customer cart merge: if both carts hold a box, keep the one already saved on the customer's
 * quote (the guest box is not merged) and tell the customer, instead of silently ending up with two boxes (or silently losing one).
 */
class MergeBoxesObserver implements ObserverInterface
{
    public function __construct(
        private readonly QuoteGuard $guard,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $source = $observer->getEvent()->getSource();
        if (!$this->guard->getBoxItems($quote)) {
            return;
        }
        $guestBoxes = $this->guard->getBoxItems($source);
        if (!$guestBoxes) {
            return;
        }
        // Runs before the merge, so the guest items are still persisted rows and can be removed cleanly.
        foreach ($guestBoxes as $item) {
            $source->removeItem($item->getItemId());
        }
        $this->messageManager->addNoticeMessage(
            __('Only one gift box is allowed per order, so the box already saved in your account was kept.')
        );
    }
}
