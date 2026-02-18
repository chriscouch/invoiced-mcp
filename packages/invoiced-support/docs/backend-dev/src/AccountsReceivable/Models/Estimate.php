<?php

namespace App\AccountsReceivable\Models;

use App\AccountsReceivable\EmailVariables\EstimateEmailVariables;
use App\AccountsReceivable\Interfaces\HasShipToInterface;
use App\AccountsReceivable\Pdf\EstimatePdf;
use App\AccountsReceivable\Pdf\EstimatePdfVariables;
use App\AccountsReceivable\Traits\HasShipToTrait;
use App\AccountsReceivable\ValueObjects\CalculatedInvoice;
use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Core\Utils\AppUrl;
use App\Core\Utils\ModelNormalizer;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\SalesTax\ValueObjects\SalesTaxInvoiceItem;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Libs\EmailHtml;
use App\Themes\Interfaces\PdfBuilderInterface;
use App\Themes\Interfaces\PdfVariablesInterface;

/**
 * @property string                $payment_terms
 * @property int|null              $expiration_date
 * @property string|null           $approved
 * @property EstimateApproval|null $approval
 * @property int|null              $invoice_id
 * @property int|null              $invoice
 * @property int                   $approval_id
 * @property float                 $deposit
 * @property bool                  $deposit_paid
 * @property int|null              $last_sent
 */
class Estimate extends ReceivableDocument implements HasShipToInterface
{
    use ApiObjectTrait;
    use HasShipToTrait;

    //
    // Hooks
    //

    protected static function getProperties(): array
    {
        return [
            'payment_terms' => new Property(
                null: true,
                validate: ['string', 'min' => 1, 'max' => 255],
            ),
            'expiration_date' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'approved' => new Property(
                null: true,
            ),
            'invoice_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
                relation: Invoice::class,
            ),
            'invoice' => new Property(
                relation: Invoice::class,
            ),
            'approval_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
                relation: EstimateApproval::class,
            ),
            'deposit' => new Property(
                type: Type::FLOAT,
            ),
            'deposit_paid' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'last_sent' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::saving([self::class, 'verifyInvoice']);
        self::saving([self::class, 'autoClosing']);
        self::saving([self::class, 'genStatus']);

        parent::initialize();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['url'] = $this->url;
        $result['pdf_url'] = $this->pdf_url;
        $shipTo = $this->ship_to;
        $result['ship_to'] = $shipTo ? $shipTo->toArray() : null;
        $approval = $this->approval;
        $result['approval'] = $approval ? $approval->toArray() : null;
        $result['metadata'] = $this->metadata;

        return $result;
    }

    protected function getMassAssignmentBlocked(): ?array
    {
        return ['subtotal', 'total', 'status', 'invoice_id', 'approval_id', 'last_sent'];
    }

    /**
     * Verifies the invoice relationship when creating.
     */
    public static function verifyInvoice(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $iid = $model->invoice_id;
        if (!$iid || $iid == $model->ignoreUnsaved()->invoice_id) {
            return;
        }

        if (!$model->invoice()) {
            throw new ListenerException("No such invoice: $iid", ['field' => 'invoice']);
        }
    }

    /**
     * Closes the estimate automatically in certain scenarios.
     */
    public static function autoClosing(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->closed || $model->closed != $model->ignoreUnsaved()->closed) {
            return;
        }

        // close estimate if being marked as approved
        // and any deposits are paid
        if ($model->approved && (!$model->deposit || $model->deposit_paid)) {
            $model->closed = true;
        }

        // close estimate if being invoiced
        if ($model->invoice_id && $model->invoice_id != $model->ignoreUnsaved()->invoice_id) {
            $model->closed = true;
        }
    }

    /**
     * Determine the estimate status before saving.
     */
    public static function genStatus(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $status = new EstimateStatus($model);
        $model->status = $status->get();

        // check if being marked as sent
        if ($model->sent && $model->sent != $model->ignoreUnsaved()->sent) {
            $model->last_sent = time();
        }
    }

    //
    // ReceivableDocument
    //

    public static function getDefaultDocumentTitle(): string
    {
        return 'Estimate';
    }

    protected function getUrlValue(): ?string
    {
        if (!$this->client_id || $this->voided) {
            return null;
        }

        return AppUrl::get()->build().'/estimates/'.$this->tenant()->identifier.'/'.$this->client_id;
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

    public function toSalesTaxDocument(CalculatedInvoice $calculatedInvoice, bool $preview = false): SalesTaxInvoice
    {
        $customer = $this->customer();
        $address = $this->getSalesTaxAddress();

        $salesTaxLines = [];
        foreach ($calculatedInvoice->items as $item) {
            $itemCode = $item['catalog_item'] ?? null;
            $salesTaxLines[] = new SalesTaxInvoiceItem($item['name'], $item['quantity'], $item['amount'], $itemCode, $item['discountable']);
        }

        return new SalesTaxInvoice($customer, $address, $calculatedInvoice->currency, $salesTaxLines, [
            'date' => $this->date,
            'number' => $this->number,
            'discounts' => $calculatedInvoice->totalDiscounts,
            // Estimates are always considered a sales order
            // which means the $preview argument is ignored
            'preview' => true,
        ]);
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

    /**
     * Gets the attached estimate approval.
     */
    protected function getApprovalValue(): ?EstimateApproval
    {
        return $this->relation('approval_id');
    }

    //
    // Relationships
    //

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

    public function getDeposit(): Money
    {
        return Money::fromDecimal($this->currency, $this->deposit);
    }

    public function getDepositBalance(): Money
    {
        if ($this->deposit_paid) {
            return Money::fromDecimal($this->currency, 0);
        }

        return $this->getDeposit();
    }

    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice_id = (int) $invoice->id();
        $this->setRelation('invoice_id', $invoice);
    }

    protected function checkIfVoidable(): void
    {
        if ($this->invoice_id) {
            throw new ModelException('This estimate cannot be voided because it has already been invoiced.');
        }

        if ($this->deposit_paid) {
            throw new ModelException('This estimate cannot be voided because it has a deposit payment applied.');
        }
    }

    /**
     * Applies a payment.
     *
     * @throws ModelException if the payment cannot be saved
     */
    public function applyPayment(Money $amount): void
    {
        // If the payment is negative (because it's added as a credit) and the estimate
        // has an unpaid deposit then this will mark it as paid.
        // This does not keep track of a balance like invoices do.
        // Limitations:
        // - This does not unmark the deposit as paid if the payment is voided.
        // - This does not account for underpayments, overpayments, or multiple payments.
        if ($amount->isNegative() && $this->deposit > 0 && !$this->deposit_paid) {
            $this->deposit_paid = true;
            $this->skipClosedCheck()
                ->saveOrFail();
        }
    }

    //
    // ThemeableInterface
    //

    public function getThemeVariables(): PdfVariablesInterface
    {
        return new EstimatePdfVariables($this);
    }

    //
    // SendableDocumentInterface
    //

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new EstimateEmailVariables($this);
    }

    public function schemaOrgActions(): ?string
    {
        $buttonText = 'View Estimate';
        $description = 'Please review your estimate';

        return EmailHtml::schemaOrgViewAction($buttonText, $this->url, $description);
    }

    public function getSendClientUrl(): ?string
    {
        return $this->url;
    }

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        return new EstimatePdf($this);
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        return [
            ['customer', $this->customer],
        ];
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['customer']);
    }

    /**
     * Get amount of money to be processed.
     */
    public function amountToProcess(): float
    {
        return $this->deposit ?? 0;
    }

    public function isReconcilable(): bool
    {
        return false;
    }

    protected function getPermissionName(): string
    {
        return 'estimates';
    }
}
