<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to the loyalty configuration (BR-07). The only class that reads these paths.
 */
class Config
{
    private const XML_PREFIX = 'codilar_loyalty/general/';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PREFIX . 'enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Points earned per currency unit spent (FR-02), as a decimal string.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAccrualRate(?int $storeId = null): string
    {
        return $this->decimal('points_per_currency_unit', '1', $storeId);
    }

    /**
     * Points needed for one currency unit of wallet balance (FR-04), as a positive decimal string.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPointsPerWalletUnit(?int $storeId = null): string
    {
        $value = $this->decimal('points_per_wallet_currency_unit', '10', $storeId);
        return Decimal::sign($value) > 0 ? $value : '10.0000';
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getMinConversionPoints(?int $storeId = null): int
    {
        return max(1, (int)$this->value('min_conversion_points', $storeId));
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isRefundToWallet(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PREFIX . 'refund_wallet_portion_to_wallet',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getExpiryMonths(?int $storeId = null): int
    {
        return max(1, (int)$this->value('expiry_months', $storeId));
    }

    /**
     * Maximum share (0-100) of an order that may be settled from the wallet (BR-04), as a decimal string.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getMaxRedemptionPercent(?int $storeId = null): string
    {
        $value = $this->decimal('max_redemption_percent', '50', $storeId);
        if (Decimal::sign($value) < 0) {
            return '0.0000';
        }
        return bccomp($value, '100', Decimal::SCALE) > 0 ? '100.0000' : $value;
    }

    /**
     * Reads a configured number as an exact decimal string; falls back to $default when absent or malformed.
     *
     * @param string $field
     * @param string $default
     * @param int|null $storeId
     * @return string
     */
    private function decimal(string $field, string $default, ?int $storeId): string
    {
        $raw = trim((string)$this->value($field, $storeId));
        return Decimal::normalize(Decimal::isValid($raw) ? $raw : $default);
    }

    /**
     * @param string $field
     * @param int|null $storeId
     * @return mixed
     */
    private function value(string $field, ?int $storeId)
    {
        return $this->scopeConfig->getValue(self::XML_PREFIX . $field, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
