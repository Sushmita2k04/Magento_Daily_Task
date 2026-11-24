<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Cron;

use Codilar\LoyaltyWallet\Model\Sweeper\Sweeper;

/**
 * Nightly run of the loyalty sweeper.
 */
class Sweep
{
    /**
     * @param Sweeper $sweeper
     */
    public function __construct(private readonly Sweeper $sweeper)
    {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->sweeper->run();
    }
}
