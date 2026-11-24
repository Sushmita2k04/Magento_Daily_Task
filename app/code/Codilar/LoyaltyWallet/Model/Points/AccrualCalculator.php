<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Points;

use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Decimal;

/**
 * Points earned for an eligible amount (FR-01, FR-02): amount x rate, fractions rounded down.
 */
class AccrualCalculator
{
    /**
     * @param Config $config
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param string $eligibleAmount Order subtotal after discounts, excluding shipping and tax
     * @param int|null $storeId
     * @return int Whole points, 0 when nothing is earned
     */
    public function calculate(string $eligibleAmount, ?int $storeId = null): int
    {
        if (!Decimal::isValid($eligibleAmount) || Decimal::sign($eligibleAmount) <= 0) {
            return 0;
        }
        return Decimal::floorToInt(
            bcmul(Decimal::normalize($eligibleAmount), $this->config->getAccrualRate($storeId), Decimal::SCALE)
        );
    }
}
