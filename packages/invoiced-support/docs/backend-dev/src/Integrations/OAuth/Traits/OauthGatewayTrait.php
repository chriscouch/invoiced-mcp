<?php

namespace App\Integrations\OAuth\Traits;

use App\PaymentProcessing\Models\MerchantAccount;
use App\Core\Orm\Exception\ModelNotFoundException;

trait OauthGatewayTrait
{
    /**
     * Try to get existing merchant account id.
     *
     * @throws ModelNotFoundException
     */
    protected function getMerchantAccount(string $gateway, string $gatewayId): ?MerchantAccount
    {
        // Check for an existing merchant account matching this gateway ID.
        // If it has been deleted then it will be restored.
        /** @var ?MerchantAccount $merchantAccount */
        $merchantAccount = MerchantAccount::where('gateway', $gateway)
            ->where('gateway_id', $gatewayId)
            ->oneOrNull();
        if ($merchantAccount) {
            if ($merchantAccount->isDeleted()) {
                $merchantAccount->restore();
            }

            return $merchantAccount;
        }

        // Check for an unused, new merchant account added through the admin panel.
        $merchantAccount = MerchantAccount::where('gateway', $gateway)
            ->where('gateway_id', '0')
            ->oneOrNull();
        if ($merchantAccount) {
            return $merchantAccount;
        }

        // Create a new merchant account.
        return null;
    }

    public function makeAccount(): MerchantAccount
    {
        return new MerchantAccount();
    }
}
