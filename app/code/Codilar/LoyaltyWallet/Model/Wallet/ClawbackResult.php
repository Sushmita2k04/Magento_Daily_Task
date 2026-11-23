<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Wallet;

/**
 * Outcome of a clawback (BR-03).
 */
class ClawbackResult
{
    /**
     * @param string $clawedBack Decimal string actually taken from the wallet
     * @param string $writtenOff Decimal string that could not be taken (recorded as a write-off)
     * @param string $walletBalance Decimal string, balance after the clawback
     * @param bool $replayed True when this request had already been processed
     */
    public function __construct(
        public readonly string $clawedBack,
        public readonly string $writtenOff,
        public readonly string $walletBalance,
        public readonly bool $replayed = false
    ) {
    }
}
