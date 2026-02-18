<?php

namespace App\EntryPoint\Command;

use App\Core\Multitenant\TenantContext;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\BillSubscription;
use Carbon\CarbonImmutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SubscriptionAdvanceCommand extends Command
{
    public function __construct(private BillSubscription $billingEngine, private TenantContext $tenant, $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('subscription:advance')
            ->setDescription('Advances a subscription to a future date')
            ->addArgument('subscription', InputArgument::REQUIRED, 'Subscription ID')
            ->addArgument('timestamp', InputArgument::REQUIRED, 'Timestamp to advance subscription to')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $subscriptionId = $input->getArgument('subscription');
        $subscription = Subscription::queryWithoutMultitenancyUnsafe()
            ->where('id', $subscriptionId)
            ->oneOrNull();

        if (!$subscription) {
            $io->error('No such subscription: '.$subscriptionId);

            return 1;
        }

        /** @var string $timestamp */
        $timestamp = $input->getArgument('timestamp');
        $date = new CarbonImmutable($timestamp);
        if ($date->getTimestamp() <= time()) {
            $io->error('Timestamp must be in the future');

            return 1;
        }
        CarbonImmutable::setTestNow($date);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($subscription->tenant());

        $hasMoreInvoices = true;
        $n = 0;
        while ($hasMoreInvoices) {
            $invoice = $this->billingEngine->bill($subscription, true);
            $hasMoreInvoices = null != $invoice;
            if ($invoice) {
                ++$n;
            }
        }

        $io->success('Subscription was advanced to '.$date->format('c').'. Generated '.$n.' invoices.');

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return 0;
    }
}
