<?php

namespace App\PaymentProcessing\Operations;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Exceptions\AutoPayException;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use App\Sending\Email\Libs\EmailSpool;
use App\Sending\Email\Libs\EmailTriggers;
use App\Sending\Email\Models\EmailTemplate;
use InvalidArgumentException;

/**
 * This class charges a customer's attached payment source
 * for a given AutoPay invoice.
 */
class AutoPay implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    const PAYMENT_PLAN_MODE_NEXT = 'next';
    const PAYMENT_PLAN_MODE_CURRENTLY_DUE = 'due_now';

    public function __construct(private ProcessPayment $processPayment, private EmailSpool $emailSpool)
    {
    }

    /**
     * Performs an AutoPay attempt for an invoice.
     *
     * @param string $paymentPlanMode determines how amount is determined when there is a payment plan
     *
     * @throws AutoPayException when there was an error performing the collection
     */
    public function collect(Invoice $invoice, string $paymentPlanMode = self::PAYMENT_PLAN_MODE_CURRENTLY_DUE, bool $notifyOnFailedAttempt = true): void
    {
        // IMPORTANT The invoice must be refreshed here
        // because the invoice can become stale in-memory
        // if it is collected out-of-band,
        // e.g. clicking the Collect Now button in the dashboard.
        // If this is not here then it can result in duplicate charges.
        $invoice->refresh();

        /** @var PaymentSource $paymentSource */
        $paymentSource = $invoice->getPaymentSource();

        // check if this invoice can be collected
        $this->verifyCanCollect($invoice, $paymentSource);

        $amount = $this->getCollectionAmount($invoice, $paymentPlanMode);
        $items = [new InvoiceChargeApplicationItem($amount, $invoice)];
        $chargeApplication = new ChargeApplication($items, PaymentFlowSource::AutoPay);

        try {
            $this->processPayment->payWithSource($paymentSource, $chargeApplication, [], null);
            $this->handleSuccess($invoice);
        } catch (ChargeException $e) {
            if (!in_array($e->reasonCode, ['duplicate_payment', 'reconciliation_failure', 'missing_merchant_account'])) {
                // INVD-3648: The invoice must be reloaded in case the reconciliation process
                // has modified some properties on the invoice that were rolled back in the database.
                $invoice->refresh();
                $invoice->handleAutoPayFailure();
                if ($notifyOnFailedAttempt && $e->charge && EmailTriggers::make($invoice->tenant())->isEnabled('autopay_failed')) {
                    $invoice->setEmailVariables([
                        'payment_source' => $paymentSource->toString(),
                        'failure_reason' => $e->getMessage(),
                        'payment_amount' => $invoice->formatMoney($e->charge->amount),
                    ]);
                    $emailTemplate = EmailTemplate::make($invoice->tenant_id, EmailTemplate::AUTOPAY_FAILED);
                    // If the AutoPay failure email fails to spool then we don't
                    // pass along the error because we want the operation
                    // to succeed.
                    $this->emailSpool->spoolDocument($invoice, $emailTemplate, [], false);
                }
            }

            $this->statsd->increment('collection_engine.failed_attempt');

            throw new AutoPayException($e->getMessage(), $e->getCode(), $e);
        }
    }

    //
    // Helper Methods
    //

    /**
     * Verifies that we can collect on a given invoice.
     *
     * @throws AutoPayException when collection is not supported for this invoice
     */
    private function verifyCanCollect(Invoice $invoice, ?PaymentSource $paymentSource): void
    {
        $this->assertAutoPayOn($invoice);
        $this->assertOutstanding($invoice);
        $this->assertPaymentPlanActive($invoice);
        $this->assertHasPaymentSource($invoice, $paymentSource);
    }

    /**
     * Get amount to collect for an invoice.
     *
     * @param string $paymentPlanMode determines how amount is determined when there is a payment plan
     */
    public function getCollectionAmount(Invoice $invoice, string $paymentPlanMode): Money
    {
        // if there is a payment plan in place then the amount we
        // are supposed to collect will be more nuanced
        if ($paymentPlan = $invoice->paymentPlan()) {
            return $this->getCollectionAmountPaymentPlan($invoice, $paymentPlan, $paymentPlanMode);
        }

        // otherwise we will collect the full balance on the invoice
        return Money::fromDecimal($invoice->currency, $invoice->balance);
    }

    /**
     * Gets the amount to be collected when there is a payment plan.
     *
     * @throws InvalidArgumentException when the mode is invalid
     */
    private function getCollectionAmountPaymentPlan(Invoice $invoice, PaymentPlan $paymentPlan, string $mode): Money
    {
        // When collecting payment plans on AutoPay, there are 2 supported modes:
        // 1. Due now - only collects installments that are currently due (based on installment date)
        // 2. Next installment - collects only the next due installment, regardless of when it's due

        if (self::PAYMENT_PLAN_MODE_NEXT == $mode) {
            foreach ($paymentPlan->installments as $installment) {
                if ($installment->balance > 0) {
                    return Money::fromDecimal($invoice->currency, $installment->balance);
                }
            }

            return new Money($invoice->currency, 0);
        }

        if (self::PAYMENT_PLAN_MODE_CURRENTLY_DUE == $mode) {
            $amount = new Money($invoice->currency, 0);
            foreach ($paymentPlan->installments as $installment) {
                if ($installment->date <= time()) {
                    $balance = Money::fromDecimal($invoice->currency, $installment->balance);
                    $amount = $amount->add($balance);
                }
            }

            return $amount;
        }

        throw new InvalidArgumentException('Invalid payment plan mode: '.$mode);
    }

    private function handleSuccess(Invoice $invoice): void
    {
        // Increment the number of payment attempts on a successful charge,
        // EXCEPT when there is a payment plan and the invoice is not paid
        // in full. If the invoice has a payment plan and is not paid in full then
        // there was a partial installment payment. In which case the payment attempt
        // count should not be incremented because it would likely have
        // been reset in reconcileCharge(). If the invoice does not have
        // a payment plan then the attempt count should always be incremented,
        // paid or not.
        if ($invoice->paid || !$invoice->paymentPlan()) {
            if (InvoiceStatus::Pending->value != $invoice->status) {
                ++$invoice->attempt_count;
            }
            $invoice->skipClosedCheck()->save();
        }

        $this->statsd->increment('collection_engine.successful_attempt');
    }

    //
    // Collection Assertions
    //

    /**
     * Asserts the invoice has AutoPay enabled.
     *
     * @throws AutoPayException when the assertion fails
     */
    private function assertAutoPayOn(Invoice $invoice): void
    {
        if (!$invoice->autopay) {
            throw new AutoPayException('Cannot collect on an invoice without AutoPay enabled.');
        }
    }

    /**
     * Asserts the invoice is outstanding.
     *
     * @throws AutoPayException when the assertion fails
     */
    private function assertOutstanding(Invoice $invoice): void
    {
        if ($invoice->closed) {
            throw new AutoPayException('Cannot collect on closed invoices. Please reopen the invoice first.', AutoPayException::EXPECTED_FAILURE_CODE);
        }

        if ($invoice->voided) {
            throw new AutoPayException('Cannot collect on voided invoices.');
        }

        if ($invoice->paid) {
            throw new AutoPayException('This invoice has already been paid.');
        }

        if ($invoice->draft) {
            throw new AutoPayException('Cannot collect on an invoice that has not been issued yet. Please issue the invoice first.');
        }
    }

    /**
     * Asserts the invoice's payment plan is active (if attached).
     *
     * @throws AutoPayException when the assertion fails
     */
    private function assertPaymentPlanActive(Invoice $invoice): void
    {
        $paymentPlan = $invoice->paymentPlan();
        if ($paymentPlan && PaymentPlan::STATUS_ACTIVE != $paymentPlan->status) {
            throw new AutoPayException('Cannot collect while there is an inactive payment plan attached to this invoice.');
        }
    }

    /**
     * Asserts the customer has an attached payment source.
     *
     * @throws AutoPayException when the assertion fails
     */
    private function assertHasPaymentSource(Invoice $invoice, ?PaymentSource $paymentSource): void
    {
        if (!$paymentSource) {
            // In all the other collection assertions
            // we do not actually want to record those as
            // failed attempts. However, when the payment source is
            // missing we want to handle the failed attempt to reschedule
            // a future collection attempt (and give the customer time
            // to add a payment source).
            $invoice->handleAutoPayFailure();

            throw new AutoPayException('Cannot collect on a customer without a payment source.', AutoPayException::EXPECTED_FAILURE_CODE);
        }

        if ($paymentSource->needsVerification()) {
            throw new AutoPayException('Cannot collect on a customer until the payment source is verified.', AutoPayException::EXPECTED_FAILURE_CODE);
        }
    }

    /**
     * Used for testing.
     */
    public function setProcessPayment(ProcessPayment $processPayment): void
    {
        $this->processPayment = $processPayment;
    }
}
