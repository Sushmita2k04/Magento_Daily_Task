<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Observer;

use Codilar\BuildYourOwnBox\Model\QuoteGuard;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Flags unavailable / ineligible boxes on the quote so the cart and checkout show an error and refuse to proceed.
 */
class ValidateBoxesObserver implements ObserverInterface
{
    private const ORIGIN = 'byob';

    public function __construct(private readonly QuoteGuard $guard)
    {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $boxes = $this->guard->getBoxItems($quote);
        if (!$boxes) {
            return;
        }
        foreach ($boxes as $item) {
            $item->removeErrorInfosByParams(['origin' => self::ORIGIN]);
        }
        $quote->removeErrorInfosByParams(null, ['origin' => self::ORIGIN]);

        foreach ($this->guard->getErrors($quote) as $error) {
            $error['item']->addErrorInfo(self::ORIGIN, 'byob_invalid', $error['message']);
            $quote->addErrorInfo('error', self::ORIGIN, 'byob_invalid', $error['message']);
        }
    }
}
