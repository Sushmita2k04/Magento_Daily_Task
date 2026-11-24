<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Console\Command;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Manual wallet credit or debit (FR-08). There is no admin screen by design; every adjustment needs a reason
 * and an operator and is recorded in the ledger.
 */
class WalletAdjust extends Command
{
    /**
     * @param WalletService $wallet
     * @param CustomerRepositoryInterface $customers
     * @param string|null $name
     */
    public function __construct(
        private readonly WalletService $wallet,
        private readonly CustomerRepositoryInterface $customers,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('codilar:wallet:adjust')
            ->setDescription('Manually credit or debit a customer wallet (reason and operator are mandatory)')
            ->addOption('customer-id', null, InputOption::VALUE_REQUIRED, 'Customer ID')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'credit or debit')
            ->addOption('amount', null, InputOption::VALUE_REQUIRED, 'Positive amount, up to 4 decimals')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Why the adjustment is made')
            ->addOption('operator', null, InputOption::VALUE_REQUIRED, 'Who is making it (name or ID)')
            ->addOption(
                'request-key',
                null,
                InputOption::VALUE_REQUIRED,
                'Optional key; repeating the same key never posts twice'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (['customer-id', 'type', 'amount', 'reason', 'operator'] as $required) {
            if (trim((string)$input->getOption($required)) === '') {
                $output->writeln(sprintf('<error>--%s is required.</error>', $required));
                return Command::INVALID;
            }
        }
        $customerId = (string)$input->getOption('customer-id');
        $type = strtolower((string)$input->getOption('type'));
        if (!ctype_digit($customerId) || (int)$customerId < 1 || !in_array($type, ['credit', 'debit'], true)) {
            $output->writeln('<error>--customer-id must be a positive integer and --type credit or debit.</error>');
            return Command::INVALID;
        }
        try {
            $customer = $this->customers->getById((int)$customerId);
            $key = trim((string)$input->getOption('request-key')) !== ''
                ? 'manual:' . trim((string)$input->getOption('request-key'))
                : 'manual:' . bin2hex(random_bytes(16));
            $movement = new WalletMovement(
                (int)$customerId,
                (string)$customer->getEmail(),
                $type === 'credit' ? W::TYPE_CREDIT_MANUAL : W::TYPE_DEBIT_MANUAL,
                (string)$input->getOption('amount'),
                trim((string)$input->getOption('reason')),
                $key,
                null,
                null,
                null,
                trim((string)$input->getOption('operator'))
            );
            $entry = $type === 'credit' ? $this->wallet->credit($movement) : $this->wallet->debit($movement);
        } catch (NoSuchEntityException) {
            $output->writeln('<error>No customer with that ID.</error>');
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln(sprintf(
            'Ledger entry %d posted: %s %s. Balance is now %s.',
            $entry->getEntryId(),
            $entry->getEntryType(),
            $entry->getAmount(),
            $entry->getBalanceAfter()
        ));
        return Command::SUCCESS;
    }
}
