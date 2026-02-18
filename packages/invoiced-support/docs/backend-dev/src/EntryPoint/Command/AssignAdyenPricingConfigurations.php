<?php

namespace App\EntryPoint\Command;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Operations\GeneratePricing;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AssignAdyenPricingConfigurations extends Command
{
    public function __construct(
        private GeneratePricing $generatePricing,
        private bool $adyenLiveMode,
        private TenantContext $tenant,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('assign-adyen-pricing-configurations')
            ->setDescription('Assigns pricing configurations to accounts onboarded before v2 pricing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accounts = AdyenAccount::queryWithoutMultitenancyUnsafe()
            ->where('account_holder_id', null, '<>')
            ->where('split_configuration_id', null)
            ->where('pricing_configuration_id', null)
            ->all()
            ->toArray();

        foreach ($accounts as $adyenAccount) {
            $company = $adyenAccount->tenant();

            // IMPORTANT: set the current tenant to enable multitenant operations
            $this->tenant->set($company);

            $parameters = AdyenConfiguration::getStandardPricing($this->adyenLiveMode, (string) $company->country, $company->currency);
            $this->generatePricing->setPricingOnMerchant($adyenAccount, $parameters);
        }

        return 0;
    }
}
