<?php

namespace App\PaymentProcessing\Interfaces;

use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;

/**
 * Interface for a payment gateway implementation.
 */
interface PaymentGatewayInterface
{
    /**
     * Validates that a gateway configuration has the correct data for this gateway.
     * This does not verify the credentials with the payment gateway, which is
     * left up to TestCredentialsInterface.
     *
     * @throws InvalidGatewayConfigurationException when the merchant account cannot be validated
     */
    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void;
}
