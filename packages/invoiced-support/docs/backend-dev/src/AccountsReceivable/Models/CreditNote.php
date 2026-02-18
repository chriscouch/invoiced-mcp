<?php

namespace App\AccountsReceivable\Models;

use App\AccountsReceivable\EmailVariables\CreditNoteEmailVariables;
use App\AccountsReceivable\Pdf\CreditNotePdf;
use App\AccountsReceivable\Pdf\CreditNotePdfVariables;
use App\AccountsReceivable\ValueObjects\CalculatedInvoice;
use App\AccountsReceivable\ValueObjects\CreditNoteStatus;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\AppUrl;
use App\Core\Utils\ModelNormalizer;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\SalesTax\ValueObjects\SalesTaxInvoiceItem;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Libs\EmailHtml;
use App\Themes\Interfaces\PdfBuilderInterface;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * This model represents a credit note document.
 *
 * @property int|null $invoice
 * @property int|null $invoice_id
 * @property float    $amount_credited
 * @property float    $amount_refunded
 * @property float    $amount_applied_to_invoice
 * @property float    $balance
 * @property bool     $paid
 * @property int      $consolidated_invoice_id
 */
class CreditNote extends ReceivableDocument
{
    private Money $applyToInvoiceLegacy;

    //
    // Hooks
    //

    protected static function getProperties(): array
    {
        return [
            'invoice_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
                relation: Invoice::class,
            ),

            /* Computed Properties */

            'amount_applied_to_invoice' => new Property(
                type: Type::FLOAT,
                in_array: false,
            ),
            'amount_refunded' => new Property(
                type: Type::FLOAT,
                in_array: false,
            ),
            'amount_credited' => new Property(
                type: Type::FLOAT,
                in_array: false,
            ),
            'balance' => new Property(
                type: Type::FLOAT,
            ),
            'paid' => new Property(
                type: Type::BOOLEAN,
            ),

            /* Consolidated Invoices */

            'consolidated_invoice_id' => new Property(
                null: true,
                in_array: false,
                relation: Invoice::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'verifyInvoice']);

        parent::initialize();

        self::creating([self::class, 'calculateCreditNote']);
        self::creating([self::class, 'calculateLegacyInvoiceApplication']);
        self::created([self::class, 'applyToInvoiceLegacy']);

        self::updating([self::class, 'calculateCreditNote'], -201);
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['url'] = $this->url;
        $result['pdf_url'] = $this->pdf_url;
        $result['invoice'] = $this->invoice;
        $result['metadata'] = $this->metadata;

        return $result;
    }

    protected function getMassAssignmentBlocked(): ?array
    {
        return ['subtotal', 'total', 'status', 'invoice_id', 'amount_applied_to_invoice', 'amount_refunded', 'amount_credited', 'paid', 'balance'];
    }

    /**
     * Verifies the invoice relationship when creating.
     */
    public static function verifyInvoice(AbstractEvent $event): void
    {
        /** @var CreditNote $creditNote */
        $creditNote = $event->getModel();

        $iid = $creditNote->invoice_id;
        if (!$iid) {
            return;
        }

        $invoice = $creditNote->invoice();
        if (!$invoice) {
            throw new ListenerException("No such invoice: $iid", ['field' => 'invoice']);
        }

        // inherit the customer ID from the invoice
        if (!$creditNote->customer) {
            $creditNote->setCustomer($invoice->customer());
        } elseif ($creditNote->customer != $invoice->customer) {
            throw new ListenerException('The invoice belongs to a different customer than the one that was specified.', ['field' => 'customer']);
        }

        if (!$creditNote->currency) {
            $creditNote->currency = $invoice->currency;
        } elseif ($creditNote->currency != $invoice->currency) {
            throw new ListenerException("The currency on this credit note ({$creditNote->currency}) must match the invoice currency ({$invoice->currency}).", ['field' => 'currency']);
        }

        unset($creditNote->invoice);
    }

    /**
     * Calculates a credit note before saving.
     */
    public static function calculateCreditNote(AbstractEvent $event): void
    {
        /** @var CreditNote $creditNote */
        $creditNote = $event->getModel();

        // calculate the balance
        $total = Money::fromDecimal($creditNote->currency, $creditNote->total);
        $appliedToInvoice = Money::fromDecimal($creditNote->currency, $creditNote->amount_applied_to_invoice ?? 0)
            ->max(new Money($creditNote->currency, 0));
        $amountCredited = Money::fromDecimal($creditNote->currency, $creditNote->amount_credited ?? 0)
            ->max(new Money($creditNote->currency, 0));
        $amountRefunded = Money::fromDecimal($creditNote->currency, $creditNote->amount_refunded ?? 0)
            ->max(new Money($creditNote->currency, 0));

        $creditNote->amount_applied_to_invoice = $appliedToInvoice->toDecimal();
        $creditNote->amount_credited = $amountCredited->toDecimal();
        $creditNote->amount_refunded = $amountRefunded->toDecimal();
        $creditNote->balance = $total->subtract($appliedToInvoice)
            ->subtract($amountCredited)
            ->subtract($amountRefunded)
            ->toDecimal();

        // voided invoices have the balance zeroed
        if ($creditNote->voided) {
            $creditNote->balance = 0;
        }

        // set paid flag
        $creditNote->paid = $creditNote->balance <= 0 && !$creditNote->draft && !$creditNote->voided;

        // close paid credit notes
        if ($creditNote->paid && !$creditNote->dirty('closed')) {
            $creditNote->closed = true;
        }

        // determine the credit note's status
        $status = new CreditNoteStatus($creditNote);
        $creditNote->status = $status->get();
    }

    /**
     * Calculates the amount to apply to the invoice.
     *
     * NOTE: Setting `invoice` is no longer recommended when creating a credit note.
     * The best practice is to create a separate Transaction model to apply a credit note
     * to an invoice.
     */
    public static function calculateLegacyInvoiceApplication(AbstractEvent $event): void
    {
        /** @var CreditNote $creditNote */
        $creditNote = $event->getModel();

        $creditNote->applyToInvoiceLegacy = new Money($creditNote->currency, 0);
        $invoice = $creditNote->invoice();
        if (!$invoice || $invoice->voided || $creditNote->draft || $creditNote->voided) {
            return;
        }

        $creditNote->applyToInvoiceLegacy = Money::fromDecimal($creditNote->currency, min($invoice->balance, $creditNote->total));
    }

    /**
     * Applies a credit note to an invoice.
     *
     * NOTE: Setting `invoice` is no longer recommended when creating a credit note.
     * The best practice is to create a separate Transaction model to apply a credit note
     * to an invoice.
     */
    public static function applyToInvoiceLegacy(AbstractEvent $event): void
    {
        /** @var CreditNote $creditNote */
        $creditNote = $event->getModel();

        if ($creditNote->applyToInvoiceLegacy->isZero()) {
            return;
        }

        $payment = new Payment();
        $payment->currency = $creditNote->currency;
        $payment->setCustomer($creditNote->customer());
        $payment->applied_to = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'credit_note' => $creditNote,
                'document_type' => 'invoice',
                'invoice' => $creditNote->invoice(),
                'amount' => $creditNote->applyToInvoiceLegacy->toDecimal(),
            ],
        ];
        $payment->saveOrFail();
    }

    //
    // ReceivableDocument
    //

    public static function getDefaultDocumentTitle(): string
    {
        return 'Credit Note';
    }

    protected function getUrlValue(): ?string
    {
        if (!$this->client_id || $this->voided) {
            return null;
        }

        return AppUrl::get()->build().'/credit_notes/'.$this->tenant()->identifier.'/'.$this->client_id;
    }

    protected function getPdfUrlValue(): ?string
    {
        $url = $this->getUrlValue();

        return $url ? "$url/pdf" : null;
    }

    protected function getCsvUrlValue(): ?string
    {
        $url = $this->getUrlValue();

        return $url ? "$url/csv" : null;
    }

    protected function getXmlUrlValue(): ?string
    {
        $url = $this->getUrlValue();

        return $url ? "$url/ubl" : null;
    }

    protected function getShipToValue(mixed $shipTo): ?ShippingDetail
    {
        $invoice = $this->invoice();
        if (!$invoice) {
            return null;
        }

        return $invoice->ship_to;
    }

    public function toSalesTaxDocument(CalculatedInvoice $calculatedInvoice, bool $preview = false): SalesTaxInvoice
    {
        $customer = $this->customer();
        $address = $this->getSalesTaxAddress();

        $options = [
            'date' => $this->date,
            'number' => $this->number,
            'discounts' => $calculatedInvoice->totalDiscounts,
            'preview' => $preview,
            'return' => true,
        ];

        if ($invoice = $this->invoice()) {
            $options['taxDate'] = $invoice->date;
        }

        $salesTaxLines = [];
        foreach ($calculatedInvoice->items as $item) {
            $itemCode = $item['catalog_item'] ?? null;
            $salesTaxLines[] = new SalesTaxInvoiceItem($item['name'], $item['quantity'], $item['amount'], $itemCode, $item['discountable']);
        }

        return new SalesTaxInvoice($customer, $address, $calculatedInvoice->currency, $salesTaxLines, $options);
    }

    //
    // Mutators
    //

    /**
     * Sets the invoice property. Alias for invoice_id.
     */
    protected function setInvoiceValue(mixed $id): mixed
    {
        $this->invoice_id = $id;

        return $id;
    }

    //
    // Accessors
    //

    /**
     * Gets the invoice property. Alias for invoice_id.
     */
    protected function getInvoiceValue(): ?int
    {
        return $this->invoice_id;
    }

    //
    // Relationships
    //

    /**
     * Sets the associated invoice.
     */
    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice_id = (int) $invoice->id();
        $this->setRelation('invoice_id', $invoice);
    }

    /**
     * Gets the associated invoice.
     */
    public function invoice(): ?Invoice
    {
        return $this->relation('invoice_id');
    }

    //
    // Utility Functions
    //

    /**
     * Adds the amount to the amount applied to an invoice and updates the balance.
     *
     * @throws ModelException
     */
    public function applyToInvoice(Money $amount): void
    {
        $this->apply($amount, 'amount_applied_to_invoice');
    }

    /**
     * Adds the amount to the amount credited and updates the balance.
     *
     * @throws ModelException
     */
    public function applyToCreditBalance(Money $amount): void
    {
        $this->apply($amount, 'amount_credited');
    }

    private function apply(Money $amount, string $property): void
    {
        $currentApplied = Money::fromDecimal($this->currency, $this->$property);
        $this->$property = $amount->add($currentApplied)->toDecimal();

        // Applying a credit note means that
        // it can no longer be a draft.
        if ($this->draft) {
            $this->draft = false;
        }

        // When the applied amount is negative, this could potentially
        // cause the credit note to go from paid -> unpaid. If this
        // happens then we should explicitly reopen the credit note.
        if ($amount->isNegative()) {
            $this->closed = false;
        }

        $this->skipClosedCheck()->saveOrFail();
    }

    /**
     * Triggers a status update on this credit note.
     *
     * @return bool true when the status was updated
     */
    public function updateStatus(): bool
    {
        $before = $this->status;

        // set a non-property to ensure the update happens as the
        // status will be calculated in the model.updating hook
        $this->_update = true; /* @phpstan-ignore-line */

        $this->save();

        return $before != $this->status;
    }

    protected function checkIfVoidable(): void
    {
        if ($this->amount_credited > 0) {
            throw new ModelException('This credit note cannot be voided because it has been added to the customer\'s credit balance.');
        }

        if ($this->amount_refunded > 0) {
            throw new ModelException('This credit note cannot be voided because it has a refund applied.');
        }

        if ($this->amount_applied_to_invoice > 0) {
            throw new ModelException('This credit note cannot be voided because it has been applied to an invoice.');
        }
    }

    //
    // SendableDocumentInterface
    //

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new CreditNoteEmailVariables($this);
    }

    public function schemaOrgActions(): ?string
    {
        $buttonText = 'View Credit Note';
        $description = 'Please review your credit note';

        return EmailHtml::schemaOrgViewAction($buttonText, $this->url, $description);
    }

    public function getSendClientUrl(): ?string
    {
        return $this->url;
    }

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        return new CreditNotePdf($this);
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        $associations = [
            ['customer', $this->customer],
        ];
        if ($this->invoice_id) {
            $associations[] = ['invoice', $this->invoice_id];
        }

        return $associations;
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['customer']);
    }

    //
    // ThemeableInterface
    //

    public function getThemeVariables(): PdfVariablesInterface
    {
        return new CreditNotePdfVariables($this);
    }

    /**
     * Get amount of money to be processed.
     *
     * @throws \Exception
     */
    public function amountToProcess(): float
    {
        throw new \Exception('This method should not be accessible');
    }

    //
    // AccountingIntegrationModelInterface
    //

    public function isReconcilable(): bool
    {
        return !$this->draft && !$this->skipReconciliation;
    }

    protected function getPermissionName(): string
    {
        return 'credit_notes';
    }
}
