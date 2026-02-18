<?php

namespace App\PaymentProcessing\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Gateways\OPPGateway;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\Refund;
use App\PaymentProcessing\Reconciliation\RefundReconciler;
use Carbon\CarbonImmutable;

/**
 * Simple interface for processing refunds that handles
 * routing to the appropriate gateway and reconciliation.
 */
class ProcessRefund implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private PaymentGatewayFactory $gatewayFactory,
        private RefundReconciler $refundReconciler,
        private GatewayLogger $gatewayLogger,
    ) {
    }

    /**
     * Issues a refund for this charge.
     *
     * @throws RefundException
     */
    public function refund(Charge $charge, Money $amount): ?Refund
    {
        $this->checkIfIsRefundable($charge, $amount);
        $merchantAccount = $charge->merchant_account;
        // This is kept for BC before we stored the merchant account on the Charge model
        if (!$merchantAccount) {
            $merchantAccount = $charge->payment_source?->getMerchantAccount();
        }

        try {
            $gateway = $this->gatewayFactory->get($charge->gateway);
            if ($merchantAccount) {
                $gateway->validateConfiguration($merchantAccount->toGatewayConfiguration());
            }
        } catch (InvalidGatewayConfigurationException $e) {
            throw new RefundException($e->getMessage());
        }

        // Check if payment gateway supports this feature
        if (!$gateway instanceof RefundInterface) {
            throw new RefundException('The `'.$charge->gateway.'` payment gateway does not support refunds');
        }

        if (!$merchantAccount) {
            $merchantAccount = new MerchantAccount();
            $merchantAccount->gateway = $charge->gateway;
        }

        // Request a refund through the payment gateway.
        $start = microtime(true);
        try {
            $refund = $gateway->refund($merchantAccount, $charge->gateway_id, $amount);
        } catch (RefundException $e) {
            $this->statsd->increment('payments.failed_refund', 1, ['gateway' => $charge->gateway]);
            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);

            throw $e;
        }

        $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
        $this->statsd->increment('payments.successful_refund', 1, ['gateway' => $charge->gateway]);

        // Refund was successful. Reconcile it.
        try {
            return $this->refundReconciler->reconcile($refund, $charge);
        } catch (ReconciliationException $e) {
            throw new RefundException($e->getMessage());
        }
    }

    /**
     * Checks if a charge can be refunded per the request.
     *
     * @throws RefundException when the charge is not refundable
     */
    private function checkIfIsRefundable(Charge $charge, Money $amount): void
    {
        // only successful charges can be refunded
        if (Charge::SUCCEEDED != $charge->status) {
            throw new RefundException('Refunds are not available for this charge because it has not cleared.');
        }

        // validate the amount
        if ($amount->isZero() || $amount->isNegative()) {
            throw new RefundException('Invalid refund amount: '.$amount);
        }

        $chargeAmount = $charge->getAmount();
        $refunded = $charge->getAmountRefunded();
        $amountAfter = $refunded->add($amount);
        if ($amountAfter->greaterThan($chargeAmount)) {
            throw new RefundException('The refund amount cannot exceed the original charge amount.');
        }

        // we allow Flywire refund without payment source
        if (FlywireGateway::ID == $charge->gateway) {
            return;
        }

        // validate the payment source
        $paymentSource = $charge->payment_source;
        // adyen and stripe VT might not result in the payment source
        if (!$paymentSource && $charge->gateway !== AdyenGateway::ID && $charge->gateway !== StripeGateway::ID) {
            throw new RefundException('Cannot refund this charge because it is missing a payment source.');
        }

        // If this is a credit card payment then a partial refund
        // is not permitted on the same day that the charge happened.
        // With most payment gateways only a void is possible before
        // the payment has settled. A partial refund on these gateways
        // can cause a bug to happen where the full refund is given
        // instead of a partial refund. This check is not going to be
        // precisely correct due to varied settlement times and time zones
        // although it should be close enough.
        $dateOfCharge = date('Y-m-d', $charge->created_at);
        $now = date('Y-m-d');
        if ($amount->lessThan($chargeAmount) && ('card' === $charge->payment_source_type || PaymentMethod::CREDIT_CARD == $paymentSource?->getMethod())) {
            $dateOfCharge = date('Y-m-d', $charge->created_at);
            $now = date('Y-m-d');
            if ($dateOfCharge == $now) {
                throw new RefundException('Partial refunds are not supported before the payment has settled. You must refund the payment in full or wait until the next day when the payment has settled.');
            }
        }

        if (AdyenGateway::ID === $charge->gateway && 'bank_account' === $charge->payment_source_type) {
            if ($fundsAvailable = $charge->merchant_account_transaction?->available_on) {
                $fundsAvailable = CarbonImmutable::instance($fundsAvailable);
            }
            if (!$fundsAvailable?->isPast()) {
                throw new RefundException('Refunds are not available for this transaction because it has not cleared.');
            }
        }
    }

    public function setGatewayFactory(PaymentGatewayFactory $gatewayFactory): void
    {
        $this->gatewayFactory = $gatewayFactory;
    }
}
