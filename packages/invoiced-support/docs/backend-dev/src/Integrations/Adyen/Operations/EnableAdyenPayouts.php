<?php

namespace App\Integrations\Adyen\Operations;

use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;

class EnableAdyenPayouts
{
    public function __construct(
        private AdyenClient $adyen,
    ) {
    }

    /**
     * Enables daily payouts for a given Adyen account.
     *
     * @throws IntegrationApiException
     */
    public function enableDailyPayouts(AdyenAccount $adyenAccount): void
    {
        $merchantAccounts = MerchantAccount::withoutDeleted()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', '0', '<>')
            ->first(100);
        foreach ($merchantAccounts as $merchantAccount) {
            $this->enableDailyPayoutsForMerchantAccount($adyenAccount, $merchantAccount);
        }
    }

    /**
     * @throws IntegrationApiException
     */
    private function enableDailyPayoutsForMerchantAccount(AdyenAccount $adyenAccount, MerchantAccount $merchantAccount): void
    {
        // Check if there is already a sweep schedule in place
        $balanceAccountId = $merchantAccount->toGatewayConfiguration()->credentials->balance_account ?? '';
        if (!$balanceAccountId) {
            return;
        }

        // If there is at least one push active sweep then do not enable payouts
        $result = $this->adyen->getSweeps($balanceAccountId);
        foreach ($result['sweeps'] as $sweep) {
            if ('active' == $sweep['status'] && 'push' == $sweep['type']) {
                return;
            }
        }

        // Find the first verified transfer instrument
        $accountHolder = $this->adyen->getAccountHolder((string) $adyenAccount->account_holder_id);
        $transferInstruments = $accountHolder['capabilities']['sendToTransferInstrument']['transferInstruments'];
        $selectedTransferInstrument = null;
        foreach ($transferInstruments as $transferInstrument) {
            if ($transferInstrument['enabled'] && $transferInstrument['allowed']) {
                $selectedTransferInstrument = $transferInstrument;
                break;
            }
        }

        if (!$selectedTransferInstrument) {
            return;
        }

        // Create a new sweep schedule
        $currency = strtoupper($merchantAccount->tenant()->currency);
        $params = [
            'category' => 'bank',
            'counterparty' => [
                'transferInstrumentId' => $selectedTransferInstrument['id'],
            ],
            'currency' => $currency,
            'schedule' => [
                'type' => 'daily',
            ],
            'type' => 'push',
            'description' => 'Daily Payout',
            'priorities' => ['regular'],
            'reference' => $merchantAccount->gateway_id,
            'referenceForBeneficiary' => 'Invoiced',
        ];
        $this->adyen->createSweep($balanceAccountId, $params);
    }
}
