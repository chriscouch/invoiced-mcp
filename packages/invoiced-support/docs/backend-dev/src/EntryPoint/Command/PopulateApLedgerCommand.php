<?php

namespace App\EntryPoint\Command;

use App\AccountsPayable\Ledger\AccountsPayableLedgerPopulator;
use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PopulateApLedgerCommand extends Command
{
    public function __construct(
        private TenantContext $tenant,
        private AccountsPayableLedgerPopulator $populator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ledger:rebuild-ap')
            ->setDescription('Builds every accounts payable ledger from scratch (This takes forever!)')
            ->addArgument(
                'tenant',
                InputArgument::REQUIRED,
                'Tenant ID'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = $input->getArgument('tenant');

        if ('all' == $tenantId) {
            foreach (Company::all() as $company) {
                if ($company->features->has('accounts_payable')) {
                    $this->syncTenant($company, $output);
                }
            }
        } else {
            $company = Company::findOrFail($tenantId);
            $this->syncTenant($company, $output);
        }

        return 0;
    }

    private function syncTenant(Company $company, OutputInterface $output): void
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $this->populator->populateLedger($company, $output);

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();
    }
}
