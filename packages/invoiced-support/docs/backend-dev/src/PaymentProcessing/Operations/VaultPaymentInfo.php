<?php

namespace App\PaymentProcessing\Operations;

use App\AccountsReceivable\Models\Customer;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;

/**
 * Simple interface for vaulting payment information that handles
 * routing to the appropriate gateway and reconciliation.
 */
class VaultPaymentInfo implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    private const PAYMENT_SOURCE_LIMIT = 20;

    public function __construct(
        private PaymentSourceReconciler $paymentSourceReconciler,
        private PaymentGatewayFactory $gatewayFactory,
        private DeletePaymentInfo $deletePaymentInfo,
        private GatewayLogger $gatewayLogger,
    ) {
    }

    /**
     * Saves payment information for a customer.
     *
     * @throws PaymentSourceException when the payment information cannot be saved
     */
    public function save(PaymentMethod $method, Customer $customer, array $parameters, bool $makeDefault = true): PaymentSource
    {
        // verify the payment method supports this operation
        if (!$method->enabled() || !$method->supportsAutoPay()) {
            throw new PaymentSourceException('Sorry, we could not save the provided payment information because this business does not support saving payment sources with the '.strtolower($method->toString()).' payment method.');
        }

        // check how many payment sources the customer currently has
        $paymentSources = $customer->paymentSources();
        if (count($paymentSources) >= self::PAYMENT_SOURCE_LIMIT) {
            throw new PaymentSourceException('The customer has reached the maximum allowed payment methods ('.self::PAYMENT_SOURCE_LIMIT.').');
        }

        // determine the gateway / merchant account to process this request
        $router = new PaymentRouter();
        /** @var MerchantAccount $merchantAccount */
        $merchantAccount = $router->getMerchantAccount($method, $customer, [], true);

        try {
            $gateway = $this->gatewayFactory->get($merchantAccount->gateway);
            $gateway->validateConfiguration($merchantAccount->toGatewayConfiguration());
        } catch (InvalidGatewayConfigurationException $e) {
            throw new PaymentSourceException($e->getMessage());
        }

        // Check if payment gateway supports this feature
        if (!$gateway instanceof PaymentSourceVaultInterface) {
            throw new PaymentSourceException('The `'.$merchantAccount->gateway.'` payment gateway does not support vaulting payment sources.');
        }

        $start = microtime(true);

        // vault the payment info on the gateway
        try {
            $source = $gateway->vaultSource($customer, $merchantAccount, $parameters);

            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
            $this->statsd->increment('payments.successful_vault', 1, ['gateway' => $merchantAccount->gateway]);
        } catch (PaymentSourceException $e) {
            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
            $this->statsd->increment('payments.failed_vault', 1, ['gateway' => $merchantAccount->gateway]);

            throw $e;
        }

        // reconcile it
        try {
            $paymentSource = $this->paymentSourceReconciler->reconcile($source);
        } catch (ReconciliationException $e) {
            throw new PaymentSourceException($e->getMessage(), $e->getCode(), $e);
        }

        // make it the customer's default, and delete the old existing one
        if ($makeDefault) {
            $customer->setDefaultPaymentSource($paymentSource, $this->deletePaymentInfo);
        }

        return $paymentSource;
    }

    public function setGatewayFactory(PaymentGatewayFactory $gatewayFactory): void
    {
        $this->gatewayFactory = $gatewayFactory;
    }
}
