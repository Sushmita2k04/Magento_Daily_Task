<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Console\Command;

use Codilar\LoyaltyWallet\Model\Reconciliation\Reconciler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Checks that balances, lots and running balances all agree with the ledgers. Read-only.
 */
class WalletReconcile extends Command
{
    /**
     * @param Reconciler $reconciler
     * @param string|null $name
     */
    public function __construct(private readonly Reconciler $reconciler, ?string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('codilar:wallet:reconcile')
            ->setDescription('Check wallet and loyalty balances against their ledgers (read-only)')
            ->addOption('customer-id', null, InputOption::VALUE_REQUIRED, 'Check one customer only');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customer = $input->getOption('customer-id');
        if ($customer !== null && (!ctype_digit((string)$customer) || (int)$customer < 1)) {
            $output->writeln('<error>--customer-id must be a positive integer.</error>');
            return Command::INVALID;
        }
        $findings = $this->reconciler->run($customer === null ? null : (int)$customer);
        if (!$findings) {
            $output->writeln('Reconciled: every balance matches its ledger.');
            return Command::SUCCESS;
        }
        foreach ($findings as $f) {
            $output->writeln(sprintf(
                '<error>customer %d: %s (expected %s, found %s)</error>',
                $f['customer_id'],
                $f['check'],
                $f['expected'],
                $f['actual']
            ));
        }
        $output->writeln(sprintf('%d discrepancies found.', count($findings)));
        return Command::FAILURE;
    }
}
