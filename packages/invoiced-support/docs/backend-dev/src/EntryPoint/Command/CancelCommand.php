<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Core\Billing\Action\CancelSubscriptionAction;
use App\Core\Billing\Exception\BillingException;
use App\Core\Multitenant\TenantContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CancelCommand extends Command
{
    public function __construct(private TenantContext $tenant, private CancelSubscriptionAction $cancelAction)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('billing:cancel')
            ->setDescription('Cancels the subscription for a company')
            ->addArgument(
                'company',
                InputArgument::REQUIRED,
                'Company ID to cancel'
            )
            ->addOption(
                'now',
                null,
                InputOption::VALUE_NONE,
                'Cancel the subscription immediately?'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('company');
        $immediately = $input->getOption('now');

        $company = $this->lookupCompany($id);
        if (!$company) {
            $output->writeln("Company # $id not found");

            return 1;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        try {
            $this->cancelAction->cancel($company, 'unspecified', !$immediately);
        } catch (BillingException $e) {
            $output->writeln("Could not cancel subscription for company # $id: ".$e->getMessage());

            return 1;
        }

        $output->writeln("Subscription canceled for company # $id");

        return 0;
    }

    /**
     * Looks up a company by ID, username, or email.
     *
     * @param string|int $id
     */
    private function lookupCompany($id): ?Company
    {
        if (is_numeric($id) && $company = Company::find($id)) {
            return $company;
        }

        // try username
        if ($company = Company::where('username', $id)->oneOrNull()) {
            return $company;
        }

        // try email
        if ($company = Company::where('email', $id)->oneOrNull()) {
            return $company;
        }

        return null;
    }
}
