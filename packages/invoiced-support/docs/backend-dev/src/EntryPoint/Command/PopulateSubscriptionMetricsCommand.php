<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\SubscriptionBilling\Metrics\MrrSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PopulateSubscriptionMetricsCommand extends Command
{
    public function __construct(
        private MrrSync $mrrSync,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('subscriptions:populate-metrics')
            ->setDescription('Populates the subscription metrics for a company')
            ->addArgument(
                'tenant',
                InputArgument::REQUIRED,
                'Tenant ID'
            )
            ->addOption(
                'refresh',
                null,
                InputOption::VALUE_NONE,
                'Generates the metrics from scratch'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = $input->getArgument('tenant');
        $refresh = $input->getOption('refresh');

        if ('all' == $tenantId) {
            foreach (Company::where('canceled', false)->all() as $company) {
                // check if the company is in good standing
                if (!$company->billingStatus()->isActive()) {
                    continue;
                }

                // must have subscriptions enabled
                if (!$company->features->has('subscriptions')) {
                    continue;
                }

                $this->mrrSync->sync($company, $output, $refresh);
            }
        } else {
            $company = Company::findOrFail($tenantId);
            $this->mrrSync->sync($company, $output, $refresh);
        }

        return 0;
    }
}
