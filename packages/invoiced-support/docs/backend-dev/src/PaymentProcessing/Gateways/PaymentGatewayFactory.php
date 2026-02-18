<?php

namespace App\PaymentProcessing\Gateways;

use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Libs\GatewayLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The responsibility of this class is to create instances
 * of payment gateway classes, referenced by the gateway ID.
 */
class PaymentGatewayFactory
{
    public function __construct(
        private ServiceLocator $gatewayLocator,
        private GatewayLogger $gatewayLogger,
    ) {
    }

    /**
     * Gets a payment gateway instance.
     *
     * @throws InvalidGatewayConfigurationException
     */
    public function get(string $id): PaymentGatewayInterface
    {
        $this->gatewayLogger->setCurrentGateway($id);
        if ($this->gatewayLocator->has($id)) {
            return $this->gatewayLocator->get($id);
        }

        throw new InvalidGatewayConfigurationException('Unknown payment gateway: '.$id);
    }
}
