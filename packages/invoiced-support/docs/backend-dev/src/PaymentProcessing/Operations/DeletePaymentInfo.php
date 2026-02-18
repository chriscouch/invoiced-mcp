<?php

namespace App\PaymentProcessing\Operations;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Models\PaymentSource;

class DeletePaymentInfo implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private PaymentGatewayFactory $gatewayFactory,
        private GatewayLogger $gatewayLogger,
    ) {
    }

    /**
     * Deletes a payment source.
     *
     * @throws PaymentSourceException
     */
    public function delete(PaymentSource $paymentSource): void
    {
        $this->deleteOnGateway($paymentSource);
        $this->deleteInDatabase($paymentSource);
    }

    private function deleteOnGateway(PaymentSource $paymentSource): void
    {
        $merchantAccount = $paymentSource->getMerchantAccount();
        try {
            $gateway = $this->gatewayFactory->get($paymentSource->gateway);
            $gateway->validateConfiguration($merchantAccount->toGatewayConfiguration());
        } catch (InvalidGatewayConfigurationException) {
            // If the payment gateway or merchant account cannot be validated then
            // we simply proceed with deleting the payment source in our database
            // versus showing an error to the user.
            return;
        }

        // Check if payment gateway supports this feature
        if (!$gateway instanceof PaymentSourceVaultInterface) {
            throw new PaymentSourceException('The `'.$paymentSource->gateway.'` payment gateway does not support deleting payment sources.');
        }

        $start = microtime(true);

        // first delete the source from the payment gateway
        try {
            $gateway->deleteSource($merchantAccount, $paymentSource);
            $this->statsd->increment('payments.delete_source', 1, ['gateway' => $paymentSource->gateway]);
        } catch (PaymentSourceException) {
            // If a payment source cannot be deleted on the gateway
            // then we ignore the error and continue to delete the
            // reference from our database.
            $this->statsd->increment('payments.failed_delete_source', 1, ['gateway' => $paymentSource->gateway]);
        }

        $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
    }

    private function deleteInDatabase(PaymentSource $paymentSource): void
    {
        if (!$paymentSource->delete()) {
            throw new PaymentSourceException('Unable to remove payment method.');
        }
    }
}
