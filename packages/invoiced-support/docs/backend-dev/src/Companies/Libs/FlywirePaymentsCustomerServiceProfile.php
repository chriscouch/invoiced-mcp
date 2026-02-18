<?php

namespace App\Companies\Libs;

use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\FlywirePaymentsOnboarding;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;

class FlywirePaymentsCustomerServiceProfile
{
    public function __construct(
        private AdyenClient $adyenClient,
        private FlywirePaymentsOnboarding $onboarding,
        private bool $adyenLiveMode,
    ) {
    }

    /**
     * @throws IntegrationApiException
     */
    public function build(AdyenAccount $adyenAccount): array
    {
        $accountHolder = null;
        if ($adyenAccount->account_holder_id) {
            $accountHolder = $this->adyenClient->getAccountHolder($adyenAccount->account_holder_id);

            // Update the account has onboarding problem to the latest value
            $hasProblems = false;
            foreach ($accountHolder['capabilities'] as $capability) {
                if (count($capability['problems'] ?? []) > 0) {
                    $hasProblems = true;
                    break;
                }
            }
            if ($hasProblems != $adyenAccount->has_onboarding_problem) {
                $adyenAccount->has_onboarding_problem = $hasProblems;
                $adyenAccount->saveOrFail();
            }
        }

        $legalEntity = null;
        if ($adyenAccount->legal_entity_id) {
            $legalEntity = $this->adyenClient->getLegalEntity($adyenAccount->legal_entity_id);
        }

        $accounts = [];
        $merchantAccounts = MerchantAccount::withoutDeleted()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', '0', '<>')
            ->all();
        foreach ($merchantAccounts as $merchantAccount) {
            $configuration = $merchantAccount->toGatewayConfiguration();
            $storeId = $configuration->credentials->store ?? '';
            $balanceAccountId = $configuration->credentials->balance_account ?? '';
            if (!$balanceAccountId || !$storeId) {
                continue;
            }

            $store = $this->adyenClient->getStore($storeId);

            $result = $this->adyenClient->getSweeps($balanceAccountId);
            $sweeps = [];
            foreach ($result['sweeps'] as $sweep) {
                if ('active' == $sweep['status']) {
                    $sweeps[] = $sweep;
                }
            }

            $accounts[] = [
                'balanceAccount' => $this->adyenClient->getBalanceAccount($balanceAccountId),
                'store' => $store,
                'sweeps' => $sweeps,
                'splitConfiguration' => $this->adyenClient->getSplitConfiguration($store['merchantId'], $store['splitConfiguration']['splitConfigurationId']),
            ];
        }

        $pricingConfiguration = AdyenConfiguration::getPricingForAccount($this->adyenLiveMode, $adyenAccount);

        $data = [
            'accountHolder' => $accountHolder,
            'accounts' => $accounts,
            'legalEntity' => $legalEntity,
            'onboardingUrl' => $this->onboarding->getOnboardingStartUrl($adyenAccount->tenant()),
            'pricingConfiguration' => $pricingConfiguration->toArray(),
        ];

        return json_decode((string) json_encode($data), true);
    }
}
