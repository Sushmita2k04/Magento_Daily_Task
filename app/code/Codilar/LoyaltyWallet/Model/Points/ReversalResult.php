<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Points;

/**
 * What a points reversal did.
 */
class ReversalResult
{
    /**
     * @param int $pointsReversed Points taken back from the customer's points balance
     * @param int $pointsClawed Converted points whose wallet value was clawed back
     * @param string $clawedBack Decimal string taken from the wallet
     * @param string $writtenOff Decimal string that could not be taken (wallet too low); recorded as a write-off
     */
    public function __construct(
        public readonly int $pointsReversed = 0,
        public readonly int $pointsClawed = 0,
        public readonly string $clawedBack = '0.0000',
        public readonly string $writtenOff = '0.0000'
    ) {
    }
}
