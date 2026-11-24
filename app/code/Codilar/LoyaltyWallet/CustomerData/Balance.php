<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\CustomerData;

use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Storefront\BalanceReader;
use Codilar\LoyaltyWallet\Model\Storefront\Money;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session;

/**
 * Private customer-data section `codilar_loyalty`. It is loaded by the browser per session, never rendered into
 * the full page cache, so a cached page can never show one customer's balance to another (NFR-02).
 */
class Balance implements SectionSourceInterface
{
    /**
     * @param Session $session
     * @param Config $config
     * @param BalanceReader $reader
     * @param Money $money
     */
    public function __construct(
        private readonly Session $session,
        private readonly Config $config,
        private readonly BalanceReader $reader,
        private readonly Money $money
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getSectionData(): array
    {
        if (!$this->config->isEnabled() || !$this->session->isLoggedIn()) {
            return ['is_enabled' => false];
        }
        $summary = $this->reader->read((int)$this->session->getCustomerId());
        return [
            'is_enabled' => true,
            'points' => $summary->points,
            'wallet' => $this->money->format($summary->wallet),
        ];
    }
}
