<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Console\Command;

use Codilar\LoyaltyWallet\Model\Points\ExpiryProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs the same expiry process as the cron job, on demand.
 */
class PointsExpire extends Command
{
    /**
     * @param ExpiryProcessor $processor
     * @param string|null $name
     */
    public function __construct(private readonly ExpiryProcessor $processor, ?string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('codilar:points:expire')
            ->setDescription('Expire loyalty points past their expiry date (safe to run repeatedly)')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report what would expire without changing anything'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('dry-run')) {
            $preview = $this->processor->preview();
            $output->writeln(
                sprintf('Dry run: %d lots, %d points would expire.', $preview['lots'], $preview['points'])
            );
            return Command::SUCCESS;
        }
        $report = $this->processor->run();
        if ($report->skipped) {
            $output->writeln('<comment>Another expiry run is in progress; nothing done.</comment>');
            return Command::SUCCESS;
        }
        $output->writeln(sprintf('Expired %d lots, %d points.', $report->lotsProcessed, $report->pointsExpired));
        if ($report->failedLotIds) {
            $output->writeln('<error>Failed lots: ' . implode(', ', $report->failedLotIds) . '</error>');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
