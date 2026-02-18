<?php

namespace App\EntryPoint\Command;

use App\Integrations\Adyen\Models\AdyenAccount;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateAdyenAccountCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate-adyen-account')
            ->setDescription('Migrates Adyen account btw tenants')
            ->addArgument(
                'from',
                InputArgument::REQUIRED,
                'Tenant id from'
            )
            ->addArgument(
                'to',
                InputArgument::REQUIRED,
                'Tenant id to'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from = $input->getArgument('from');
        $to = $input->getArgument('to');

        $adyenAccount = AdyenAccount::queryWithoutMultitenancyUnsafe()->where('tenant_id', $from)->one();
        /** @var MerchantAccount[] $stores */
        $stores = MerchantAccount::queryWithoutMultitenancyUnsafe()->where('tenant_id', $from)
            ->where('gateway', AdyenGateway::ID)
            ->all();

        $newAccount = AdyenAccount::queryWithoutMultitenancyUnsafe()->where('tenant_id', $to)->oneOrNull() ?? new AdyenAccount();
        $newAccount->tenant_id = $to;
        $newAccount->legal_entity_id = $adyenAccount->legal_entity_id;
        $newAccount->business_line_id = $adyenAccount->business_line_id;
        $newAccount->store_id = $adyenAccount->store_id;
        $newAccount->reference = $adyenAccount->reference;
        $newAccount->account_holder_id = $adyenAccount->account_holder_id;
        $newAccount->balance_account_id = $adyenAccount->balance_account_id;
        $newAccount->industry_code = $adyenAccount->industry_code;
        $newAccount->terms_of_service_acceptance_date = $adyenAccount->terms_of_service_acceptance_date;
        $newAccount->terms_of_service_acceptance_ip = $adyenAccount->terms_of_service_acceptance_ip;
        $newAccount->terms_of_service_acceptance_version = $adyenAccount->terms_of_service_acceptance_version;
        $newAccount->terms_of_service_acceptance_user = $adyenAccount->terms_of_service_acceptance_user;
        $newAccount->pricing_configuration = $adyenAccount->pricing_configuration;
        $newAccount->onboarding_started_at = $adyenAccount->onboarding_started_at;
        $newAccount->activated_at = $adyenAccount->activated_at;
        $newAccount->last_onboarding_reminder_sent = $adyenAccount->last_onboarding_reminder_sent;
        $newAccount->has_onboarding_problem = $adyenAccount->has_onboarding_problem;
        $newAccount->statement_descriptor = $adyenAccount->statement_descriptor;

        $newAccount->saveOrFail();

        foreach ($stores as $store) {
            /** @var MerchantAccount $newStore */
            $newStore = MerchantAccount::queryWithoutMultitenancyUnsafe()
                ->where('tenant_id', $to)
                ->where('deleted', 0)
                ->where('gateway_id', $store->gateway_id)
                ->where('gateway', $store->gateway)
                ->oneOrNull()
                ?? new MerchantAccount();
            $newStore->tenant_id = $to;
            $newStore->gateway_id = $store->gateway_id;
            $newStore->credentials = $store->credentials;
            $newStore->settings = $store->settings;
            $newStore->gateway = $store->gateway;
            $newStore->name = $store->name;
            $newStore->top_up_threshold_num_of_days = $store->top_up_threshold_num_of_days;

            $newStore->saveOrFail();
        }


        return 0;
    }
}
