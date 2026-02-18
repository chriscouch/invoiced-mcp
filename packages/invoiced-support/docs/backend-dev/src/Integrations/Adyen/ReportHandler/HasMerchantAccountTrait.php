<?php

namespace App\Integrations\Adyen\ReportHandler;

use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;

trait HasMerchantAccountTrait
{
    /** @var MerchantAccount[] */
    private array $merchantAccounts = [];

    protected function getMerchantAccount(?string $gatewayId): ?MerchantAccount
    {
        if (!$gatewayId) {
            return null;
        }

        if (isset($this->merchantAccounts[$gatewayId])) {
            return $this->merchantAccounts[$gatewayId];
        }

        $merchantAccount = MerchantAccount::queryWithoutMultitenancyUnsafe()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', $gatewayId)
            ->oneOrNull();
        if ($merchantAccount) {
            $this->merchantAccounts[$gatewayId] = $merchantAccount;
        }

        return $merchantAccount;
    }
}
