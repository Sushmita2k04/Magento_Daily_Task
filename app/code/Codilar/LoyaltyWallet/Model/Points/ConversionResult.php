<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Points;

/**
 * Outcome of a points-to-wallet conversion.
 */
class ConversionResult
{
    /**
     * @param int $pointsConverted
     * @param string $walletAmount Decimal string credited to the wallet
     * @param int $pointsBalance Balance after the conversion
     * @param string $walletBalance Decimal string, balance after the conversion
     * @param bool $replayed True when this request had already been processed
     */
    public function __construct(
        public readonly int $pointsConverted,
        public readonly string $walletAmount,
        public readonly int $pointsBalance,
        public readonly string $walletBalance,
        public readonly bool $replayed = false
    ) {
    }
}
