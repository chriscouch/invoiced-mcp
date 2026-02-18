<?php

namespace App\EntryPoint\Command;

use App\Core\Billing\Audit\BillingAudit;
use App\Core\Cron\ValueObjects\Run;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BillingAuditCommand extends Command
{
    public function __construct(
        private BillingAudit $billingAudit,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('billing:audit')
            ->setDescription('Compares Invoiced data with the billing system to detect discrepancies')
            ->addOption(
                'rebuild',
                null,
                InputOption::VALUE_NONE,
                'This option will rebuild all subscriptions that do not have a discrepancy'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rebuild = $input->getOption('rebuild');
        $output->writeln('Checking for discrepancies between Invoiced and billing system... (this will take awhile)');

        $run = new Run();
        $run->setConsoleOutput($output);
        $discrepancies = $this->billingAudit->auditAll($run, $rebuild);

        if (count($discrepancies) > 0) {
            $url = $this->billingAudit->saveOutput();
            $output->writeln('Results saved to '.$url);
        }

        return 0;
    }
}
