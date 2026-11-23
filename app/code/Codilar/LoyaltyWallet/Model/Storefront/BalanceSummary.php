<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Storefront;

/**
 * What a customer may see and spend right now.
 */
class BalanceSummary
{
    /**
     * @param int $points Spendable points (expired-but-not-yet-processed points excluded)
     * @param string $wallet Wallet balance as a scale-4 decimal string
     * @param array{expires_at: string, points: int}|null $nextExpiry Earliest expiry day with points due (UTC)
     */
    public function __construct(
        public readonly int $points,
        public readonly string $wallet,
        public readonly ?array $nextExpiry
    ) {
    }
}
