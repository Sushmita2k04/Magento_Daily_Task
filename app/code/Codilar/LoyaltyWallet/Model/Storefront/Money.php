<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Storefront;

use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Framework\Locale\CurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Display formatting only: rounds a decimal string half-up to 2 places and adds the store currency symbol.
 * No arithmetic on money happens here.
 */
class Money
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param CurrencyInterface $localeCurrency
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CurrencyInterface $localeCurrency
    ) {
    }

    /**
     * @param string $amount Decimal string, scale up to 4
     * @return string e.g. "₹1,234.50"
     */
    public function format(string $amount): string
    {
        $rounded = Decimal::normalize(bcadd($amount, Decimal::sign($amount) < 0 ? '-0.005' : '0.005', 4));
        $twoPlaces = bcadd(substr($rounded, 0, -2), '0', 2);
        $code = $this->storeManager->getStore()->getBaseCurrencyCode();
        $currency = $this->localeCurrency->getCurrency($code);
        $symbol = $currency->getSymbol() ?: $code;
        [$int, $frac] = explode('.', $twoPlaces);
        return $symbol . number_format((int)$int, 0, '.', ',') . '.' . $frac;
    }
}
