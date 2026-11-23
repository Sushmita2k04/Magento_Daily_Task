<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Cron;

use Codilar\LoyaltyWallet\Model\Points\ExpiryProcessor;
use Psr\Log\LoggerInterface;

/**
 * Daily expiry of loyalty points.
 */
class ExpirePoints
{
    /**
     * @param ExpiryProcessor $processor
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ExpiryProcessor $processor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $report = $this->processor->run();
        $this->logger->info(sprintf(
            'Loyalty expiry: %d lots, %d points%s%s',
            $report->lotsProcessed,
            $report->pointsExpired,
            $report->skipped ? ' (skipped: another run in progress)' : '',
            $report->failedLotIds ? ' (failed lots: ' . implode(',', $report->failedLotIds) . ')' : ''
        ));
    }
}
