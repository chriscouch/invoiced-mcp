<?php

namespace App\PaymentProcessing\Operations;

use App\AccountsReceivable\Models\Invoice;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\Database\TransactionManager;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\Sending\Email\Libs\EmailSpool;
use App\Sending\Email\Libs\EmailTriggers;
use App\Sending\Email\Models\EmailTemplate;

/**
 * Simple interface for updating charge status that handles
 * routing to the appropriate gateway and reconciliation.
 */
class UpdateChargeStatus implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private PaymentGatewayFactory $gatewayFactory,
        private EventSpool $eventSpool,
        private EmailSpool $emailSpool,
        private TransactionManager $transaction,
        private NotificationSpool $notificationSpool,
        private GatewayLogger $gatewayLogger,
    ) {
    }

    /**
     * Checks and updates a charge status.
     *
     * @throws TransactionStatusException
     */
    public function update(Charge $charge): bool
    {
        $paymentSource = $charge->payment_source;
        if (!$paymentSource) {
            return false;
        }

        $merchantAccount = $paymentSource->getMerchantAccount();
        try {
            $gateway = $this->gatewayFactory->get($paymentSource->gateway);
            $gateway->validateConfiguration($merchantAccount->toGatewayConfiguration());
        } catch (InvalidGatewayConfigurationException) {
            return false;
        }

        // Check if payment gateway supports this feature
        if (!$gateway instanceof TransactionStatusInterface) {
            return false;
        }

        $start = microtime(true);

        // look up the status from the gateway
        try {
            [$status, $message] = $gateway->getTransactionStatus($merchantAccount, $charge);
            $this->statsd->increment('payments.successful_check_status', 1, ['gateway' => $charge->gateway]);
        } catch (TransactionStatusException) {
            $this->statsd->increment('payments.failed_check_status', 1, ['gateway' => $charge->gateway]);

            return false;
        }

        $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);

        if (Charge::PENDING == $status) {
            // if the charge is beyond a certain point
            // then we were unable to lookup the status for
            // an unknown reason. We are going to consider the
            // payment succeeded at this point to be safe.
            if ($charge->created_at < strtotime('-30 days')) {
                $method = $charge->payment?->method;
                $status = PaymentMethod::AFFIRM === $method || PaymentMethod::KLARNA === $method ? Charge::FAILED : Charge::SUCCEEDED;

                $message = 'The payment was automatically marked as succeeded because it did not clear within 30 days.';
            } else {
                // mark the last status check timestamp for the pending charge cron job
                $charge->last_status_check = time();
                $charge->save();

                return false;
            }
        }

        // here we refresh the charge in case it has already been
        // updated asynchronously by a webhook while waiting in the queue.
        $charge->refresh();
        if ($charge->status == $status) {
            return false;
        }

        $this->saveStatus($charge, $status, $message);

        return true;
    }

    /**
     * Updates the status of the charge and performs the relevant activities associated
     * with updating the charge status.
     * WARNING: This method should only be called if the charge status has changed.
     *
     * @throws TransactionStatusException
     */
    public function saveStatus(Charge $charge, string $status, ?string $message = null): void
    {
        // update the charge with the new status
        $charge->status = $status;
        if ($message) {
            $charge->failure_message = $message;
        }

        // wrap in database transaction
        $invoices = $this->transaction->perform(fn () => $this->performSave($charge, $status, $message));

        // email a receipt
        $payment = $charge->payment;
        if (Charge::SUCCEEDED == $status && $payment) {
            if ($charge->customer_id && 'autopay' === $payment->source) {
                $this->notificationSpool->spool(NotificationEventType::AutoPaySucceeded, $charge->tenant_id, $charge->id, $charge->customer_id);
            }
            $this->sendReceipt($payment, $charge);
        }

        if (Charge::FAILED == $status) {
            // notify the customer
            foreach ($invoices as $invoice) {
                $this->sendAutoPayFailureNotice($charge, $invoice);
            }

            // record an internal notification
            if ($charge->customer_id && $payment && 'autopay' === $payment->source) {
                $this->notificationSpool->spool(NotificationEventType::AutoPayFailed, $charge->tenant_id, $charge->id, $charge->customer_id);
            }
        }

        // create the activity log event
        $associations = [];
        foreach ($invoices as $invoice) {
            $associations[] = [$invoice->object, $invoice->id()];
        }
        $this->createActivityLog($charge, $associations);
    }

    /**
     * @throws TransactionStatusException
     */
    private function performSave(Charge $charge, string $status, ?string $message): array
    {
        $priorStatus = $charge->ignoreUnsaved()->status;

        if (!$charge->save()) {
            throw new TransactionStatusException('Could not save charge: '.$charge->getErrors());
        }

        // Update the associated payment flow
        if ($paymentFlow = $charge->payment_flow) {
            $this->updatePaymentFlow($charge, $paymentFlow);
        }

        // Update the associated payment, if it exists and is not already voided
        $payment = $charge->payment;
        $invoices = [];
        if ($payment && !$payment->voided) {
            // when the payment fails then it should be voided
            // collect invoices to send out AutoPay notices
            if (Charge::FAILED == $status) {
                $transactions = $payment->getTransactions();

                //we void payment prior to updating transactions
                //if original status was success
                if (Charge::SUCCEEDED === $priorStatus) {
                    $payment->void();
                }

                foreach ($transactions as $transaction) {
                    if ($invoice = $transaction->invoice()) {
                        $invoices[] = $invoice;

                        $invoice->setFromPendingToFailed()
                            ->handleAutoPayFailure();
                    }
                }

                //we void payment after to updating transactions otherwise
                if (Charge::SUCCEEDED !== $priorStatus) {
                    $payment->void();
                }
            } else {
                // when the payment succeeds then it should update
                // related transactions with the new status
                foreach ($payment->getTransactions() as $transaction) {
                    $this->updateTransaction($transaction, $status, $message);
                }
            }
        }

        return $invoices;
    }

    /**
     * @throws TransactionStatusException
     */
    private function updateTransaction(Transaction $transaction, string $status, ?string $message): void
    {
        $transaction->status = $status;
        if ($message) {
            $transaction->failure_reason = $message;
        }

        if (!$transaction->save()) {
            // When the transaction cannot be reconciled because it
            // causes an overpayment, mark the payment as failed
            // and record why as the failure reason.
            if ($transaction->getErrors()->has('overpayment', 'reason')) {
                $transaction->status = Transaction::STATUS_FAILED;
                $transaction->failure_reason = 'The payment was successful but we were unable to reconcile it. '.$transaction->getErrors();
                $transaction->save();

                return;
            }

            throw new TransactionStatusException('Could not update transaction: '.$transaction->getErrors());
        }
    }

    private function createActivityLog(Charge $charge, array $associations): void
    {
        $type = match ($charge->status) {
            Charge::PENDING => EventType::ChargePending,
            Charge::FAILED => EventType::ChargeFailed,
            default => EventType::ChargeSucceeded,
        };
        $pendingEvent = new PendingEvent(
            object: $charge,
            type: $type,
            associations: $associations
        );
        $this->eventSpool->enqueue($pendingEvent);
    }

    /**
     * Updates the status of the associated payment flow.
     */
    private function updatePaymentFlow(Charge $charge, PaymentFlow $paymentFlow): void
    {
        $paymentFlow->status = match ($charge->status) {
            ChargeValueObject::SUCCEEDED => PaymentFlowStatus::Succeeded,
            ChargeValueObject::FAILED => PaymentFlowStatus::Failed,
            default => PaymentFlowStatus::Processing,
        };
        $paymentFlow->save();
    }

    /**
     * Sends a payment receipt (if enabled).
     */
    private function sendReceipt(Payment $payment, Charge $charge): void
    {
        if (EmailTriggers::make($charge->tenant())->isEnabled('new_charge')) {
            $receiptEmail = [];
            $paymentSource = $charge->payment_source;
            if ($paymentSource && $email = $paymentSource->receipt_email) {
                $receiptEmail[] = ['email' => $email];
            }

            $emailTemplate = EmailTemplate::make($payment->tenant_id, EmailTemplate::PAYMENT_RECEIPT);
            // If the receipt email fails to spool then we don't
            // pass along the error because we want the operation
            // to succeed.
            $this->emailSpool->spoolDocument($payment, $emailTemplate, $receiptEmail, false);
        }
    }

    /**
     * Sends an AutoPay failed payment notice (if enabled).
     */
    private function sendAutoPayFailureNotice(Charge $charge, Invoice $invoice): void
    {
        if (EmailTriggers::make($charge->tenant())->isEnabled('autopay_failed')) {
            $source = $charge->payment_source;
            $invoice->setEmailVariables([
                'payment_source' => $source ? $source->toString() : null,
                'failure_reason' => $charge->failure_message,
                'payment_amount' => $invoice->formatMoney($charge->getAmount()),
            ]);
            $emailTemplate = EmailTemplate::make($invoice->tenant_id, EmailTemplate::AUTOPAY_FAILED);
            // If the AutoPay failure email fails to spool then we don't
            // pass along the error because we want the operation
            // to succeed.
            $this->emailSpool->spoolDocument($invoice, $emailTemplate, [], false);
        }
    }

    /**
     * Used for testing.
     */
    public function setGatewayFactory(PaymentGatewayFactory $gatewayFactory): void
    {
        $this->gatewayFactory = $gatewayFactory;
    }
}
