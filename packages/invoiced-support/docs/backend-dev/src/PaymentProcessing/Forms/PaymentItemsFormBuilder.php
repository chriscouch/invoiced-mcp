<?php

namespace App\PaymentProcessing\Forms;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\CashApplication\Models\CreditBalance;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use App\PaymentProcessing\ValueObjects\PaymentItemsForm;

/**
 * This class builds the payment item selection form.
 */
final class PaymentItemsFormBuilder
{
    /** @var ReceivableDocument[] */
    private array $documents = [];
    /** @var string[] */
    private array $selectedDocuments = [];
    private Money $creditBalance;

    /**
     * @param int[] $customerIds
     */
    public function __construct(
        private readonly PaymentFormSettings $settings,
        private readonly Customer $customer,
        private readonly array $customerIds
    ) {
        $this->addOpenItems();
    }

    public function build(): PaymentItemsForm
    {
        return new PaymentItemsForm(
            company: $this->settings->company,
            customer: $this->customer,
            documents: $this->documents,
            advancePayment: $this->settings->allowAdvancePayments,
            selectedDocuments: $this->selectedDocuments,
            creditBalance: $this->creditBalance,
        );
    }

    /**
     * Selects an invoice that is available given an invoice #.
     */
    public function selectInvoiceByNumber(string $number): void
    {
        $this->selectDocument(ObjectType::Invoice, 'number', $number);
    }

    /**
     * Selects an estimate that is available given an estimate #.
     */
    public function selectEstimateByNumber(string $number): void
    {
        $this->selectDocument(ObjectType::Estimate, 'number', $number);
    }

    /**
     * Selects a credit note that is available given a credit note #.
     */
    public function selectCreditNoteByNumber(string $number): void
    {
        $this->selectDocument(ObjectType::CreditNote, 'number', $number);
    }

    /**
     * Indicates that the user has selected their credit balance.
     */
    public function selectCreditBalance(): void
    {
        $this->selectedDocuments[] = PaymentItemsForm::CREDIT_BALANCE_KEY;
    }

    /**
     * Indicates that the user has selected an advance payment.
     */
    public function selectAdvancePayment(): void
    {
        $this->selectedDocuments[] = PaymentItemsForm::ADVANCE_PAYMENT_KEY;
    }

    private function selectDocument(ObjectType $type, string $property, string $value): void
    {
        foreach ($this->documents as $document) {
            if ($document->object == $type->typeName() && $document->{$property} == $value) {
                $this->selectedDocuments[] = $document->client_id;
                break;
            }
        }
    }

    /**
     * Adds all open items to the payment form.
     */
    private function addOpenItems(): void
    {
        if ($this->settings->company->features->has('estimates')) {
            $this->addOpenEstimates();
        }
        $this->addOpenInvoices();
        if ($this->settings->allowApplyingCredits) {
            $this->addOpenCreditNotes();
            $this->addCreditBalance();
        } else {
            $this->creditBalance = Money::zero($this->customer->calculatePrimaryCurrency());
        }
    }

    /**
     * Adds all open estimates requiring a deposit payment to the payment form.
     */
    private function addOpenEstimates(): void
    {
        $estimates = Estimate::where('customer', $this->customerIds)
            ->where('draft', false)
            ->where('closed', false)
            ->where('voided', false)
            ->where('deposit_paid', false)
            ->where('approved', null, '<>')
            ->where('invoice_id', null)
            ->where('deposit', 0, '>')
            ->where('status', EstimateStatus::EXPIRED, '<>')
            ->sort('date ASC,id ASC')
            ->all();

        foreach ($estimates as $estimate) {
            $this->documents[] = $estimate;
        }
    }

    /**
     * Adds all open invoices to the payment form.
     */
    private function addOpenInvoices(): void
    {
        $invoices = Invoice::where('customer', $this->customerIds)
            ->join(Customer::class, 'customer', 'id')
            ->where('date', time(), '<=')
            ->where('draft', false)
            ->where('voided', false)
            ->where('closed', false)
            ->where('paid', false)
            ->where('status', InvoiceStatus::Pending->value, '<>')
            ->sort('date ASC,id ASC')
            ->all();

        foreach ($invoices as $invoice) {
            $this->documents[] = $invoice;
        }
    }

    /**
     * Adds all open credit notes to the payment form.
     */
    private function addOpenCreditNotes(): void
    {
        $creditNotes = CreditNote::where('customer', $this->customerIds)
            ->where('date', time(), '<=')
            ->where('draft', false)
            ->where('voided', false)
            ->where('closed', false)
            ->where('paid', false)
            ->sort('date ASC,id ASC')
            ->all();

        foreach ($creditNotes as $creditNote) {
            $this->documents[] = $creditNote;
        }
    }

    /**
     * Adds the customer credit balance to the payment form.
     */
    private function addCreditBalance(): void
    {
        $this->creditBalance = CreditBalance::lookup($this->customer);
    }
}
