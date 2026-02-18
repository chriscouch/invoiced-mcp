<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AdyenFixPaymeMethodsCommand extends Command
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AdyenClient $adyen,
        private readonly bool $adyenLiveMode,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('adyen:fix:stores')
            ->setDescription('Retries to add stores')
            ->addArgument(
                'tenant',
                InputArgument::REQUIRED,
                'Company ID to process'
            )
            ->addArgument(
                'currency',
                InputArgument::REQUIRED,
                'Comma separated list of currencies to add'
            )
            ->addArgument(
                'replace',
                InputArgument::OPTIONAL,
                'Replace mode, 0 to add only missing, 1 to replace all',
                '0'
            )
            ->addArgument(
                'methods',
                InputArgument::OPTIONAL,
                'Comma separated list of payment method to add ie: amex, affirm, klarna, etc',
                'amex'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantIds = explode(',', $input->getArgument('tenant'));
        $currencies = explode(',', $input->getArgument('currency'));
        $methods = explode(',', $input->getArgument('methods'));
        $replace = $input->getArgument('replace');

        foreach ($tenantIds as $tenantId) {
            $tenant = Company::findOrFail($tenantId);
            $this->tenant->set($tenant);
            /** @var AdyenAccount $adyenAccount */
            $adyenAccount = AdyenAccount::one();
            $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, $tenant->country ?? 'US');

            /** @var MerchantAccount[] $stores */
            $stores = MerchantAccount::withoutDeleted()
                ->where('gateway', AdyenGateway::ID)
                ->all();
            foreach ($stores as $store) {
                $this->processStore($output, $tenant, $adyenMerchantAccount, $adyenAccount, $store, $currencies, $methods, $replace);
            }
        }
        return 0;
    }

    private function processStore(OutputInterface $output, Company $tenant, string $adyenMerchantAccount, AdyenAccount $adyenAccount, MerchantAccount $store, array $currencies, array $methods, int $replace): void
    {
        $pageSize = 100;
        $hasMore = true;
        $pageNumber = 1;
        while ($hasMore) {
            try {
                $storeId = $store->credentials->store;

                $paymentMethods = $this->adyen->getPaymentMethodSettings($adyenMerchantAccount, [
                    'pageSize' => $pageSize,
                    'pageNumber' => $pageNumber,
                    'businessLineId' => $adyenAccount->business_line_id,
                    'storeId' => $storeId,
                ]);
                $data = $paymentMethods['data'] ?? [];

                foreach ($data as $paymentMethod) {
                    if (in_array($paymentMethod['type'], $methods)) {
                        $savedCurrencies = $replace ? $currencies : array_values(array_unique(array_merge($paymentMethod['currencies'], $currencies)));

                        try {
                            $this->adyen->updatePaymentMethodSettings($adyenMerchantAccount, $paymentMethod['id'], [
                                'currencies' => $savedCurrencies,
                            ]);
                            $output->writeln("Updated payment method - {$tenant->name}:$storeId:{$paymentMethod['type']} with currencies: " . implode(',', $savedCurrencies));
                        } catch (IntegrationApiException $e) {
                            $output->writeln("Error processing payment method - {$tenant->name}:$storeId:{$paymentMethod['type']}: " . $e->getMessage());
                        }
                    }
                }

                if (count($data) < $pageSize) {
                    return;
                }
                ++$pageNumber;
            } catch (IntegrationApiException $e) {
                $output->writeln("Error processing store - {$tenant->name}:$storeId: " . $e->getMessage());
                return;
            }
        }
    }
}
