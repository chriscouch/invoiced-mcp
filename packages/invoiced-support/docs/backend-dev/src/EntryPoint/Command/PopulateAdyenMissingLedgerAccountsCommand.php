<?php

namespace App\EntryPoint\Command;

use App\Core\Ledger\Enums\AccountType;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Enums\MerchantAccountLedgerAccounts;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PopulateAdyenMissingLedgerAccountsCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly TenantContext         $tenant,
        private readonly MerchantAccountLedger $merchantAccountLedger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('populate-adyen-missing-ledger-accounts')
            ->setDescription('Populates missing Adyen ledger accounts for merchant accounts.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the ledger to populate accounts for.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = str_replace('~', ' ', $input->getArgument('name'));
        //validate argument
        $account = MerchantAccountLedgerAccounts::from($name);

        $accounts = MerchantAccount::queryWithoutMultitenancyUnsafe()
            ->where('deleted', false)
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', '0', '<>')
            ->all()
            ->toArray();

        $n = 0;
        foreach ($accounts as $merchantAccount) {
            $company = $merchantAccount->tenant();

            // IMPORTANT: set the current tenant to enable multitenant operations
            $this->tenant->set($company);

            $ledger = $this->merchantAccountLedger->getLedger($merchantAccount);
            $ledger->chartOfAccounts->findOrCreate($account->value, AccountType::Revenue, $ledger->baseCurrency);
            ++$n;
        }

        $output->writeln("Created $n missing ledger accounts");

        return 0;
    }
}
