<?php

declare(strict_types=1);

namespace Codilar\LoginPopup\Observer;

use Codilar\LoginPopup\Model\Logger\Logger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class LoginPopup implements ObserverInterface

    /**
     * @param Logger $logger
     */
{
    public function __construct(
        private readonly Logger $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->logger->info('Customer logged in successfully.');
    }
}
