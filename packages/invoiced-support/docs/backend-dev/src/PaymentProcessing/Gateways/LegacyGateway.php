<?php

namespace App\PaymentProcessing\Gateways;

use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;

/**
 * This is only used for testing the legacy payment gateway API.
 */
class LegacyGateway extends AbstractGateway
{
    const ID = 'legacy';

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
    }

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        throw new ChargeException('Not implemented');
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        // do nothing
    }
}
