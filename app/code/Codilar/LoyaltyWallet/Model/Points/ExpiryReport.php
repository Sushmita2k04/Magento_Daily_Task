<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Points;

/**
 * Outcome of one expiry run.
 */
class ExpiryReport
{
    /**
     * @param int $lotsProcessed Lots closed by this run
     * @param int $pointsExpired Points removed by this run
     * @param int[] $failedLotIds Lots that raised an error and were left for the next run
     * @param bool $skipped True when another run already held the lock
     */
    public function __construct(
        public readonly int $lotsProcessed,
        public readonly int $pointsExpired,
        public readonly array $failedLotIds = [],
        public readonly bool $skipped = false
    ) {
    }
}
