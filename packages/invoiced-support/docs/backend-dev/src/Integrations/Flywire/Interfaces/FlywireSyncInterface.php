<?php

namespace App\Integrations\Flywire\Interfaces;

use App\PaymentProcessing\Models\MerchantAccount;

interface FlywireSyncInterface
{
    /**
     * Performs a daily sync of a particular Flywire data type.
     *
     * @param bool $fullSync when this is true it will sync the entire record set for the account
     */
    public function sync(MerchantAccount $merchantAccount, array $portalCodes, bool $fullSync): void;
}
