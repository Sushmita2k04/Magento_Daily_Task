<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Plugin;

use Codilar\BuildYourOwnBox\Model\QuoteGuard;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;

/**
 * Last line of defence: refuses to place an order whose box is no longer eligible or available.
 */
class ValidateBoxesOnSubmitPlugin
{
    public function __construct(private readonly QuoteGuard $guard)
    {
    }

    /**
     * @throws LocalizedException
     */
    public function beforeSubmit(QuoteManagement $subject, Quote $quote, $orderData = []): void
    {
        foreach ($this->guard->getErrors($quote) as $error) {
            throw new LocalizedException(__($error['message']));
        }
    }
}
