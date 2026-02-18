<?php

namespace App\Integrations\GoCardless;

use App\PaymentProcessing\Models\MerchantAccount;
use GoCardlessPro\Client;

/**
 * API client wrapper for GoCardless.
 */
class GoCardlessApi
{
    /**
     * Builds a GoCardless client.
     */
    public function getClient(MerchantAccount $merchantAccount): Client
    {
        return new Client([
            'access_token' => $merchantAccount->credentials->access_token,
            'environment' => $merchantAccount->credentials->environment,
        ]);
    }
}
