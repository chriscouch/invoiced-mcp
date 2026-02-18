<?php

namespace App\EntryPoint\Command;

use App\Core\Multitenant\TenantContext;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\Integrations\Stripe\ReconcileStripePaymentFlow;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixMissingStripePaymentsCommand extends Command
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly ReconcileStripePaymentFlow $reconcileStripePaymentFlow,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:missing_stripe_payments')
            ->setDescription('Fixes missing Adyen Payments.')
            ->addArgument(
                'identifier',
                InputArgument::REQUIRED,
                'PaymentFlow identifier to restore.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('identifier');
        /** @var PaymentFlow $flow */
        $flow = PaymentFlow::queryWithoutMultitenancyUnsafe()
            ->where('identifier', $id)
            ->one();

        $this->tenant->set($flow->tenant());

        /** @var MerchantAccount[] $merchantAccounts */
        $merchantAccounts = MerchantAccount::where('gateway', StripeGateway::ID)
            ->execute();

        if (1 === count($merchantAccounts)) {
            $flow->gateway = StripeGateway::ID;
            $flow->merchant_account = $merchantAccounts[0];
            $flow->save();
        }

        try {
            $this->reconcileStripePaymentFlow->reconcile($flow);
        } catch (FormException|PaymentLinkException|ChargeException $e) {
            $output->writeln($e->getMessage());
        } catch (TransactionStatusException $e) {
            $output->writeln('Stripe API Failure: '.$e->getMessage());
        }

        $this->tenant->clear();

        return 0;
    }
}
