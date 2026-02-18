<?php

namespace App\PaymentProcessing\Interfaces;

use App\PaymentProcessing\Exceptions\TestGatewayCredentialsException;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;

/**
 * Interface for handling testing of credentials against payment gateways.
 */
interface TestCredentialsInterface
{
    /**
     * Tests given payment gateway credentials by attempting to connect to payment gateway.
     *
     * @throws TestGatewayCredentialsException when the credentials do not work
     */
    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void;
}
