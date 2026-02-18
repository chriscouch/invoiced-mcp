<?php

namespace App\PaymentProcessing\Forms;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\PaymentProcessing\Enums\PaymentAmountOption;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Libs\PaymentAmountCalculator;
use App\PaymentProcessing\ValueObjects\PaymentAmountForm;
use App\PaymentProcessing\ValueObjects\PaymentAmountFormItem;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;

/**
 * This class builds the payment amount selection form.
 */
final class PaymentAmountFormBuilder
{
    private array $lineItems = [];
    private string $currency;

    public function __construct(
        private PaymentFormSettings $settings,
        private Customer $customer,
    ) {
    }

    public function build(): PaymentAmountForm
    {
        if (!isset($this->currency)) {
            $this->currency = $this->customer->currency ?? $this->settings->company->currency;
        }

        return new PaymentAmountForm(
            company: $this->settings->company,
            customer: $this->customer,
            lineItems: $this->lineItems,
            currency: $this->currency,
        );
    }

    /**
     * Adds an invoice given an invoice client ID.
     *
     * @throws FormException
     */
    public function addInvoiceByClientId(string $clientId): void
    {
        $invoice = Invoice::findClientId($clientId);
        if (!$invoice) {
            throw new FormException('Could not find invoice: '.$clientId);
        }

        $this->canAddDocument($invoice);

        $options = [];

        // Payment plan option when there is one attached.
        if ($invoice->payment_plan_id) {
            $options[] = PaymentAmountOption::PaymentPlan;
        }

        // Pay in full option (always present).
        $options[] = PaymentAmountOption::PayInFull;

        // Allow a partial invoice payment if enabled.
        if ($this->settings->allowPartialPayments) {
            $options[] = PaymentAmountOption::PayPartial;
        }

        $this->lineItems[] = $this->makeItem($invoice, $options, null);
    }

    /**
     * Adds an estimate given an estimate client ID.
     *
     * @throws FormException
     */
    public function addEstimateByClientId(string $clientId): void
    {
        $estimate = Estimate::findClientId($clientId);
        if (!$estimate) {
            throw new FormException('Could not find estimate: '.$clientId);
        }

        $this->canAddDocument($estimate);

        // The only option for an estimate is to pay the deposit amount in full.
        $this->lineItems[] = $this->makeItem($estimate, [PaymentAmountOption::PayInFull], null);
    }

    /**
     * Adds a credit note given a credit note client ID.
     *
     * @throws FormException
     */
    public function addCreditNoteByClientId(string $clientId): void
    {
        // Do nothing if this setting is not enabled.
        if (!$this->settings->allowApplyingCredits) {
            return;
        }

        $creditNote = CreditNote::findClientId($clientId);
        if (!$creditNote) {
            throw new FormException('Could not find credit note: '.$clientId);
        }

        $this->canAddDocument($creditNote);

        // The only option on a credit note is to apply the full balance.
        // This will only apply the credit note balance to the amount owed
        // until the balance is used. This happens before any cash is collected.
        $this->lineItems[] = $this->makeItem($creditNote, [PaymentAmountOption::ApplyCredit], null);
    }

    /**
     * Adds a credit balance to the payment.
     */
    public function addCreditBalance(): void
    {
        // Do nothing if this setting is not enabled.
        if ($this->settings->allowApplyingCredits) {
            $this->lineItems[] = $this->makeItem(null, [PaymentAmountOption::ApplyCredit], $this->customer);
        }
    }

    /**
     * Adds an advance payment.
     */
    public function addAdvancePayment(): void
    {
        // Do nothing if this setting is not enabled.
        if ($this->settings->allowAdvancePayments) {
            $this->lineItems[] = $this->makeItem(null, [PaymentAmountOption::AdvancePayment], null);
        }
    }

    /**
     * @throws FormException
     */
    private function canAddDocument(ReceivableDocument $document): void
    {
        // must match any existing currency
        if (isset($this->currency)) {
            if ($document->currency != $this->currency) {
                throw new FormException('Cannot add '.$document->number.' because the transaction currency ('.strtoupper($document->currency).') does not match the form currency ('.strtoupper($this->currency).').');
            }
        } else {
            $this->currency = $document->currency;
        }
    }

    /**
     * @param PaymentAmountOption[] $options
     */
    private function makeItem(?ReceivableDocument $document, array $options, ?Customer $customer): PaymentAmountFormItem
    {
        $calculator = new PaymentAmountCalculator();
        $result = [];
        foreach ($options as $option) {
            $result[] = [
                'type' => $option,
                'amount' => $calculator->calculate($document, $option, $customer),
            ];
        }

        $nonDocumentType = null;
        if (!$document) {
            $nonDocumentType = $customer ? 'creditBalance' : 'advance';
        }

        return new PaymentAmountFormItem($result, $document, $nonDocumentType);
    }
}
