<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Models\MerchantAccount;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetOPPTargetsGatewayCommand extends Command
{
    public function __construct(private TenantContext $tenant)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('get-opp-target-gateway')
            ->setDescription('Adds OPP gateway for testing')
            ->addArgument(
                'tenant_ids',
                InputArgument::REQUIRED,
                'Company ID or username to add user to'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ids = $input->getArgument('tenant_ids');
        foreach (explode(",", $ids) as $id) {
            $company = Company::findOrFail($id);
            $this->tenant->set($company);

            $accounts = MerchantAccount::withoutDeleted()->all();
            $output->writeln("Compoany id,Compoany name,Gateway,Credentials");
            foreach ($accounts as $account) {
                $output->writeln(implode(",", [
                    $company->id,
                    $company->name,
                    $account->gateway,
                    json_encode($account->credentials),
                ]));
            }
        }

        return 0;
    }
}
