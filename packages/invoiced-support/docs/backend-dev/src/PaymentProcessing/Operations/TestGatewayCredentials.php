<?php

namespace App\PaymentProcessing\Operations;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\TestGatewayCredentialsException;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\TestCredentialsInterface;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;

class TestGatewayCredentials implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private PaymentGatewayFactory $gatewayFactory,
    ) {
    }

    /**
     * @throws TestGatewayCredentialsException
     */
    public function testCredentials(string $gatewayId, array $credentials): void
    {
        $account = new PaymentGatewayConfiguration($gatewayId, (object) $credentials);

        try {
            $gateway = $this->gatewayFactory->get($gatewayId);
            $gateway->validateConfiguration($account);
        } catch (InvalidGatewayConfigurationException $e) {
            throw new TestGatewayCredentialsException($e->getMessage());
        }

        if (!($gateway instanceof TestCredentialsInterface)) {
            throw new TestGatewayCredentialsException("The `$gatewayId` payment gateway does not support testing credentials");
        }

        try {
            $gateway->testCredentials($account);
        } catch (TestGatewayCredentialsException $e) {
            $this->statsd->increment('payments.failed_test', 1, ['gateway' => $gatewayId]);

            throw $e;
        }

        $this->statsd->increment('payments.successful_test', 1, ['gateway' => $gatewayId]);
    }
}
