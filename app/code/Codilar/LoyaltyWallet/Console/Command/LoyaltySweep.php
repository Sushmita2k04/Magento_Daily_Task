<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Console\Command;

use Codilar\LoyaltyWallet\Model\Sweeper\Sweeper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Releases wallet debits whose order was never saved and accrues points for orders that missed them.
 */
class LoyaltySweep extends Command
{
    /**
     * @param Sweeper $sweeper
     * @param string|null $name
     */
    public function __construct(private readonly Sweeper $sweeper, ?string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('codilar:loyalty:sweep')
            ->setDescription('Release stuck wallet reservations and accrue points missed at order placement');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->sweeper->run();
        $output->writeln(sprintf(
            'Released %d reservations, accrued points for %d orders, processed %d reversals, %d failures.',
            $report['released'],
            $report['accrued'],
            $report['reversed'],
            $report['failed']
        ));
        return $report['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
