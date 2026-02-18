<?php

namespace App\PaymentProcessing\Operations;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Models\Payment;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Exceptions\ChargeDeclinedException;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\OneTimeChargeInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Libs\InitiatedChargeFactory;
use App\PaymentProcessing\Libs\PaymentLock;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\InitiatedCharge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Reconciliation\ChargeReconciler;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Lock\LockFactory;
use Throwable;

/**
 * Simple interface for processing payments that handles
 * routing to the appropriate gateway and reconciliation.
 */
class ProcessPayment implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    const LOCK_TTL = 3600;

    /** @var PaymentLock[] */
    private array $locks;

    public function __construct(
        private PaymentGatewayFactory $gatewayFactory,
        private LockFactory $lockFactory,
        private ChargeReconciler $chargeReconciler,
        private InitiatedChargeFactory $initiatedChargeFactory,
        private GatewayLogger $gatewayLogger,
    ) {
    }

    /**
     * Performs a one-time charge for an estimate or one or more invoices
     * and reconciles it.
     *
     * @throws ChargeException
     */
    public function pay(PaymentMethod $method, Customer $customer, ChargeApplication $chargeApplication, array $parameters, ?PaymentFlow $paymentFlow, bool $applyConvenienceFee = true): ?Payment
    {
        // build charge application
        $documents = $chargeApplication->getNonCreditDocuments();

        $router = new PaymentRouter();
        try {
            /** @var MerchantAccount $merchantAccount */
            $merchantAccount = $router->getMerchantAccount($method, $customer, $documents, true);
            $paymentFlow?->setMerchantAccount($merchantAccount);
        } catch (InvalidArgumentException) {
            throw new ChargeException('Payments cannot be processed without a merchant account.', null, 'missing_merchant_account');
        }

        // flywire bank transfers do not create charges
        if (FlywireGateway::ID === $merchantAccount->gateway && isset($parameters['paymentMethod']) && 'bank_transfer' === $parameters['paymentMethod']) {
            return null;
        }

        // validate the selected payment items
        $chargeApplication->validate($customer, $merchantAccount->gateway, $method->id);

        // convenience fees
        $convenienceFee = null;
        if ($applyConvenienceFee) {
            $convenienceFee = $chargeApplication->applyConvenienceFee($method, $customer);
            $paymentFlow?->applyConvenienceFee($convenienceFee);
        }

        $receiptEmail = $parameters['receipt_email'] ?? null;
        $this->setMutexLock($documents);

        try {
            $gateway = $this->gatewayFactory->get($merchantAccount->gateway);
            $gateway->validateConfiguration($merchantAccount->toGatewayConfiguration());
        } catch (InvalidGatewayConfigurationException $e) {
            throw new ChargeException($e->getMessage());
        }

        // Check if payment gateway supports this feature
        if (!$gateway instanceof OneTimeChargeInterface) {
            throw new ChargeException('The `'.$merchantAccount->gateway.'` payment gateway does not support one-time payments.');
        }

        $initiatedCharge = $this->initiatedChargeFactory->create(null, $customer, $merchantAccount, $chargeApplication, $parameters);

        $start = microtime(true);

        // submit the payment to the payment gateway
        try {
            $description = GatewayHelper::makeDescription($documents);
            $charge = $gateway->charge($customer, $merchantAccount, $chargeApplication->getPaymentAmount(), $parameters, $description, $documents);

            if ($paymentFlow) {
                $paymentFlow->status = PaymentFlowStatus::Processing;
                $paymentFlow->save();
            }

            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
            $this->statsd->increment('payments.successful_charge', 1, ['gateway' => $merchantAccount->gateway, 'tokenized' => '0']);

            if (!$charge->method) {
                $charge = $charge->withMethod($method->id);
            }

            try {
                $initiatedCharge->setCharge($charge);
            } catch (Throwable) {
            }
        } catch (ChargeException $e) {
            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
            $this->statsd->increment('payments.failed_charge', 1, ['gateway' => $merchantAccount->gateway, 'tokenized' => '0']);

            $this->handleFailedPayment($e, $method, $chargeApplication, $initiatedCharge, $receiptEmail, $paymentFlow);

            // rethrow
            throw $e;
        }

        return $this->handleSuccessfulPayment($charge, $method, $chargeApplication, $initiatedCharge, $receiptEmail, $paymentFlow);
    }

    /**
     * Performs a one-time charge for an estimate or one or more invoices
     * using the given payment source and reconciles the charge.
     *
     * @throws ChargeException
     */
    public function payWithSource(PaymentSource $source, ChargeApplication $chargeApplication, array $parameters, ?PaymentFlow $paymentFlow, bool $applyConvenienceFee = true): ?Payment
    {
        $method = $source->getPaymentMethod();
        $customer = $source->customer;

        // validate the selected payment items
        $chargeApplication->validate($customer, $source->gateway, $method->id);

        // convenience fees
        $convenienceFee = null;
        if ($applyConvenienceFee) {
            $convenienceFee = $chargeApplication->applyConvenienceFee($method, $customer);
            $paymentFlow?->applyConvenienceFee($convenienceFee);
        }

        $receiptEmail = $parameters['receipt_email'] ?? $source->receipt_email;

        // lock the documents the charge is for
        $documents = $chargeApplication->getNonCreditDocuments();
        $this->setMutexLock($documents);

        // determine the gateway / merchant account to process this request
        $merchantAccount = $source->getMerchantAccount();
        $paymentFlow?->setMerchantAccount($merchantAccount);

        try {
            $gateway = $this->gatewayFactory->get($source->gateway);
            $gateway->validateConfiguration($merchantAccount->toGatewayConfiguration());
        } catch (InvalidGatewayConfigurationException $e) {
            throw new ChargeException($e->getMessage());
        }

        // Check if payment gateway supports this feature
        if (!$gateway instanceof PaymentSourceVaultInterface) {
            throw new ChargeException('The `'.$source->gateway.'` payment gateway does not support charging payment sources.');
        }

        // provide simple validation on the cvc, if provided
        if (array_key_exists('cvc', $parameters)) {
            if (preg_match("/[^\d]/", $parameters['cvc'])) {
                throw new ChargeException('Invalid CVC number. The CVC number should only contain digits.');
            }

            // Amex is 4 digits, other cards are 3
            $len = strlen($parameters['cvc']);
            if ($len < 3 || $len > 4) {
                throw new ChargeException("The provided CVC was of the incorrect length ($len digits given).");
            }
        }

        // block non-debit cards when the debit cards only setting is in place
        if ($source instanceof Card) {
            if ($source->tenant()->accounts_receivable_settings->debit_cards_only && 'debit' != $source->funding) {
                throw new ChargeException('Only debit cards are permitted for payments.');
            }
        }

        $initiatedCharge = $this->initiatedChargeFactory->create($source, $customer, $merchantAccount, $chargeApplication, $parameters);

        $start = microtime(true);

        try {
            $description = GatewayHelper::makeDescription($documents);
            $charge = $gateway->chargeSource($source, $chargeApplication->getPaymentAmount(), $parameters, $description, $documents);
            if ($paymentFlow) {
                $paymentFlow->status = PaymentFlowStatus::Processing;
                $paymentFlow->save();
            }

            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
            $this->statsd->increment('payments.successful_charge', 1, ['gateway' => $merchantAccount->gateway, 'tokenized' => '1']);

            try {
                $initiatedCharge->setCharge($charge);
            } catch (Throwable) {
            }
        } catch (ChargeException $e) {
            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
            $this->statsd->increment('payments.failed_charge', 1, ['gateway' => $merchantAccount->gateway, 'tokenized' => '1']);

            $this->handleFailedPayment($e, $method, $chargeApplication, $initiatedCharge, $receiptEmail, $paymentFlow);

            // rethrow
            throw $e;
        }

        return $this->handleSuccessfulPayment($charge, $method, $chargeApplication, $initiatedCharge, $receiptEmail, $paymentFlow);
    }

    /**
     * Grab the mutex lock.
     *
     * @throws ChargeException
     */
    public function setMutexLock(array $documents): void
    {
        $locks = [];
        foreach ($documents as $document) {
            // document will be null on advance payment.
            if (!$document) {
                continue;
            }

            $lock = $this->getLock($document);
            if (!$lock->acquire(self::LOCK_TTL)) {
                throw new ChargeException('Duplicate payment attempt detected.', null, 'duplicate_payment');
            }
            $locks[] = $lock;
        }
        $this->locks = $locks;
    }

    /**
     * Release mutex locks.
     */
    private function releaseMutexLock(): void
    {
        foreach ($this->locks as $lock) {
            $lock->release();
        }
    }

    //
    // Helpers
    //

    /**
     * Creates an instance of a payment mutex lock for a document.
     */
    private function getLock(ReceivableDocument $document): PaymentLock
    {
        return new PaymentLock($document, $this->lockFactory);
    }

    /**
     * Handles a failed payment attempt.
     */
    public function handleFailedPayment(ChargeException $e, PaymentMethod $method, ChargeApplication $chargeApplication, InitiatedCharge $initiatedCharge, ?string $receiptEmail, ?PaymentFlow $paymentFlow): void
    {
        // reconcile the failed payment attempt
        // but only if the gateway and transaction ID are provided
        $charge = $e->charge;
        if ($charge && $charge->gateway && $charge->gatewayId) {
            try {
                $this->reconcileCharge($charge, $method, $chargeApplication, $receiptEmail, $paymentFlow);
            } catch (ChargeException) {
                // do nothing, this is just wrapper to handle exception added in reconcileCharge
            }
        }

        $initiatedCharge->delete();
        $this->releaseMutexLock();
    }

    /**
     * Handles a successful payment attempt.
     */
    public function handleSuccessfulPayment(ChargeValueObject $charge, PaymentMethod $method, ChargeApplication $chargeApplication, InitiatedCharge $initiatedCharge, ?string $receiptEmail, ?PaymentFlow $paymentFlow): ?Payment
    {
        try {
            $payment = $this->reconcileCharge($charge, $method, $chargeApplication, $receiptEmail, $paymentFlow);
        } catch (ChargeDeclinedException $e) {
            $initiatedCharge->delete();
            $this->releaseMutexLock();
            throw $e;
        }

        $initiatedCharge->delete();
        $this->releaseMutexLock();

        return $payment;
    }

    /**
     * @throws ChargeException|ChargeDeclinedException
     */
    public function reconcileCharge(ChargeValueObject $charge, PaymentMethod $method, ChargeApplication $chargeApplication, ?string $receiptEmail, ?PaymentFlow $paymentFlow): ?Payment
    {
        // The charge might not have the payment method included, so we
        // add it on here.
        if (!$charge->method) {
            $charge = $charge->withMethod($method->id);
        }

        // reconcile the charge
        try {
            $charge = $this->chargeReconciler->reconcile($charge, $chargeApplication, $paymentFlow, $receiptEmail);
        } catch (ReconciliationException $e) {
            $this->logger->emergency('Unable to reconcile charge when processing payment', ['exception' => $e]);

            // Only show this message if the payment was successful but we were not able to save it.
            if (in_array($charge->status, [Charge::SUCCEEDED, Charge::PENDING])) {
                throw new ChargeException('Your payment was successfully processed but could not be saved. Please do not retry payment.', null, 'reconciliation_failure');
            }

            // This should only happen if the charge had a failed status
            // and we could not save it to the database.
            return null;
        }

        if ($charge?->payment) {
            return $charge->payment;
        }

        if ($charge && PaymentFlowSource::CustomerPortal === $chargeApplication->getPaymentSource() && AdyenGateway::ID === $method->gateway) {
            throw new ChargeDeclinedException($charge);
        }

        return null;
    }

    /**
     * Used for testing.
     */
    public function setGatewayFactory(PaymentGatewayFactory $gatewayFactory): void
    {
        $this->gatewayFactory = $gatewayFactory;
    }
}
