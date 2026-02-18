<?php

namespace App\Integrations\Adyen\Operations;

use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Models\MerchantAccount;

class GetAccountInformation
{
    public function __construct(
        private readonly AdyenClient $adyenClient,
    ) {
    }

    /**
     * @throws IntegrationApiException
     */
    public function balanceAccount(MerchantAccount $merchantAccount): ?array
    {
        $balanceAccountId = $merchantAccount->toGatewayConfiguration()->credentials->balance_account ?? '';
        if (!$balanceAccountId) {
            return null;
        }

        return $this->adyenClient->getBalanceAccount($balanceAccountId);
    }

    /**
     * @throws IntegrationApiException
     */
    public function legalEntity(AdyenAccount $adyenAccount): ?array
    {
        if (!$adyenAccount->legal_entity_id) {
            return null;
        }

        return $this->adyenClient->getLegalEntity($adyenAccount->legal_entity_id);
    }

    /**
     * @throws IntegrationApiException
     */
    public function accountHolder(AdyenAccount $adyenAccount): ?array
    {
        if (!$adyenAccount->account_holder_id) {
            return null;
        }

        return $this->adyenClient->getAccountHolder($adyenAccount->account_holder_id);
    }

    /**
     * @throws IntegrationApiException
     */
    public function sweep(MerchantAccount $merchantAccount): ?array
    {
        $balanceAccountId = $merchantAccount->toGatewayConfiguration()->credentials->balance_account ?? '';
        if (!$balanceAccountId) {
            return null;
        }

        $result = $this->adyenClient->getSweeps($balanceAccountId);

        // Look for the first active sweep
        foreach ($result['sweeps'] as $sweep) {
            if ('active' != $sweep['status']) {
                continue;
            }

            $transferInstrumentId = $sweep['counterparty']['transferInstrumentId'] ?? null;

            return [
                'bank_name' => $transferInstrumentId ? $this->adyenClient->getBankAccountName($transferInstrumentId) : null,
                'frequency' => $sweep['schedule']['type'] ?? null,
                'cron_expression' => $sweep['schedule']['cronExpression'] ?? null,
            ];
        }

        return null;
    }
}
