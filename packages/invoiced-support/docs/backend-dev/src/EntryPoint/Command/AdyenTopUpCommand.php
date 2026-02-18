<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\Operations\RunAdyenTopUpProcedure;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AdyenTopUpCommand extends Command
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RunAdyenTopUpProcedure $runAdyenTopUpProcedure
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('adyen:top-up')
            ->setDescription('Adds a user as a member of a company')
            ->addArgument(
                'company',
                InputArgument::REQUIRED,
                'Invoiced Company ID'
            )
            ->addArgument(
                'instrument',
                InputArgument::OPTIONAL,
                'Transfer instrument ID'
            )
            ->addOption(
                'dry-run',
                '-d',
                InputOption::VALUE_NONE,
                'Do not execute the payment',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('company');
        $instrument = $input->getArgument('instrument') ?? null;
        $dryRun = $input->getOption('dry-run');

        $company = Company::find($id);
        if (!$company) {
            $output->writeln("Company # $id not found");

            return 1;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $merchantAccount = MerchantAccount::withoutDeleted()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', '0', '<>')
            ->sort('id ASC')
            ->oneOrNull();

        $this->runAdyenTopUpProcedure->perform($merchantAccount, $company, $instrument, $dryRun);

        return 0;
    }
}
