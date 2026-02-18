<?php

namespace App\AccountsReceivable\Models;

use App\AccountsReceivable\Exception\InvoiceCalculationException;
use App\AccountsReceivable\Libs\InvoiceCalculator;
use App\AccountsReceivable\Traits\HasCustomerTrait;
use App\AccountsReceivable\Traits\HasDiscountsTrait;
use App\AccountsReceivable\Traits\HasLineItemsTrait;
use App\AccountsReceivable\Traits\HasShippingTrait;
use App\AccountsReceivable\Traits\HasTaxesTrait;
use App\AccountsReceivable\Traits\HasTransactionsQuotaTrait;
use App\AccountsReceivable\ValueObjects\CalculatedInvoice;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventModelTrait;
use App\Companies\Models\Member;
use App\Companies\Traits\HasAutoNumberingTrait;
use App\Companies\Traits\MoneyTrait;
use App\Core\Files\Traits\HasAttachmentsTrait;
use App\Core\I18n\AddressFormatter;
use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\HasCustomerRestrictionsTrait;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Event\ModelUpdating;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Pdf\PdfDocumentInterface;
use App\Core\Queue\QueueFacade;
use App\Core\Queue\QueueServiceLevel;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Search\Traits\SearchableTrait;
use App\Core\Utils\Traits\HasClientIdTrait;
use App\Core\Utils\Traits\HasModelLockTrait;
use App\EntryPoint\QueueJob\SendNetworkDocumentQueueJob;
use App\Integrations\AccountingSync\Models\AccountingWritableModel;
use App\Integrations\AccountingSync\ValueObjects\InvoicedObjectReference;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Traits\MetadataTrait;
use App\Network\Models\NetworkDocument;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\SalesTax\Exception\TaxCalculationException;
use App\SalesTax\Interfaces\TaxCalculatorInterface;
use App\SalesTax\Libs\TaxCalculatorFactoryFacade;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Sending\Email\Libs\EmailSpoolFacade;
use App\Sending\Email\Traits\SendableDocumentTrait;
use App\Themes\Interfaces\ThemeableInterface;
use App\Themes\Traits\ThemeableTrait;
use Carbon\CarbonImmutable;
use CommerceGuys\Addressing\Address;
use DateTimeInterface;
use ICanBoogie\Inflector;

/**
 * This is the base class that all documents, like invoices, credit notes,
 * and estimates, are derived from.
 *
 * @property int                  $id
 * @property string               $name
 * @property string               $currency
 * @property int                  $date
 * @property string|null          $purchase_order
 * @property string               $notes
 * @property bool                 $draft
 * @property bool                 $closed
 * @property bool                 $voided
 * @property int|null             $date_voided
 * @property bool                 $sent
 * @property bool                 $viewed
 * @property string               $status
 * @property float                $subtotal
 * @property float                $total
 * @property array                $items
 * @property ShippingDetail|null  $ship_to
 * @property string|null          $url
 * @property string|null          $pdf_url
 * @property string|null          $csv_url
 * @property string|null          $xml_url
 * @property bool|null            $calculate_taxes
 * @property NetworkDocument|null $network_document
 * @property int|null             $network_document_id
 */
abstract class ReceivableDocument extends AccountingWritableModel implements EventObjectInterface, MetadataModelInterface, PdfDocumentInterface, SendableDocumentInterface, ThemeableInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;
    use HasCustomerTrait;
    use HasDiscountsTrait;
    use HasTaxesTrait;
    use HasShippingTrait;
    use HasAttachmentsTrait;
    use HasAutoNumberingTrait;
    use HasClientIdTrait;
    use HasLineItemsTrait;
    use HasCustomerRestrictionsTrait;
    use MetadataTrait;
    use MoneyTrait;
    use SearchableTrait;
    use SendableDocumentTrait;
    use ThemeableTrait;
    use HasTransactionsQuotaTrait;
    use HasModelLockTrait;

    protected static array $recalculateProperties = [
        'items',
        'discounts',
        'taxes',
        'shipping',
        'tax',
        'discount',
    ];

    protected bool $_send = false;
    protected bool $_wasIssued = false;
    private bool $_skipClosedCheck = false;
    private bool $ignoreUnsavedArray = false;
    private TaxCalculatorInterface $taxCalculator;

    public function isReconcilable(): bool
    {
        return true;
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'isIssueCreate'], 1001);
        self::updating([self::class, 'isIssueUpdate'], 1001);

        self::creating([static::class, 'verifyCustomer'], 2);
        self::creating([static::class, 'verifyActiveCustomer'], 1);
        // Calculation should happen after a document # has been assigned to the document
        self::creating([static::class, 'calculateDocument']);
        self::updating([static::class, 'protectCustomer']);

        self::updating([self::class, 'voidAssessedTaxes']);
        self::updating([static::class, 'beforeUpdate'], -200);

        self::deleting([self::class, 'checkDelete']);
        self::deleting([self::class, 'voidAssessedTaxes']);

        self::saved([static::class, 'saveModelRelationships']);
        self::saved([static::class, 'sendAfterIssue'], -1);
        self::created([self::class, 'bustCurrenciesCache']);

        self::autoInitializeModelLock();

        parent::initialize();
    }

    protected static function autoDefinitionReceivableDocument(): array
    {
        return [
            'name' => new Property(
                default: static::getDefaultDocumentTitle(),
            ),
            'number' => new Property(
                validate: ['string', 'min' => 1, 'max' => 32],
            ),
            'currency' => new Property(
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'date' => new Property(
                type: Type::DATE_UNIX,
                required: true,
                validate: 'timestamp',
                default: 'now',
            ),
            'purchase_order' => new Property(
                null: true,
                validate: ['string', 'min' => 0, 'max' => 32],
            ),
            'notes' => new Property(
                null: true,
            ),
            'draft' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'closed' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'voided' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
            'date_voided' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
            'network_document' => new Property(
                null: true,
                belongs_to: NetworkDocument::class,
            ),

            /* Computed Properties */

            'subtotal' => new Property(
                type: Type::FLOAT,
            ),
            'total' => new Property(
                type: Type::FLOAT,
                validate: ['callable', 'fn' => [self::class, 'notNegative']],
            ),
            'sent' => new Property(
                type: Type::BOOLEAN,
                default: false,
                in_array: false,
            ),
            'viewed' => new Property(
                type: Type::BOOLEAN,
                default: false,
                in_array: false,
            ),
            'status' => new Property(),
        ];
    }

    /**
     * Gets the title for this type of document.
     */
    abstract public static function getDefaultDocumentTitle(): string;

    /**
     * Skips the check to prevent editing closed invoices.
     *
     * @return $this
     */
    public function skipClosedCheck()
    {
        $this->_skipClosedCheck = true;

        return $this;
    }

    /**
     * Gets the money formatting options for this object.
     */
    public function moneyFormat(): array
    {
        return $this->customer()->moneyFormat();
    }

    //
    // Hooks
    //

    /**
     * Calculates the document when creating.
     */
    public static function calculateDocument(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $company = $model->tenant();
        $customer = $model->customer();

        // Fall back to customer currency then
        // to company currency if none given.
        if (!$model->currency) {
            $model->currency = $customer->currency ?? $company->currency;
        }

        // ensure a date is provided
        if (!$model->date) {
            $model->date = time();
        }

        /* Sanitize items/rates - all should be arrays */

        // handle flat amounts, i.e. 'tax' => 12.34
        foreach (['discount' => 'discounts', 'tax' => 'taxes', 'shipping' => 'shipping'] as $amountKey => $arrayKey) {
            if (isset($model->$amountKey) && is_numeric($model->$amountKey)) {
                $model->$arrayKey = [['amount' => $model->$amountKey]];
                unset($model->$amountKey);
            }
        }

        // fill in missing properties
        foreach (self::$recalculateProperties as $k) {
            if (!is_array($model->$k)) {
                $model->$k = [];
            }
        }

        /* Calculate the Invoice */

        try {
            $calculatedInvoice = InvoiceCalculator::prepare($model->currency, $model->items, $model->discounts, $model->taxes, $model->shipping);
            InvoiceCalculator::calculateInvoice($calculatedInvoice);
        } catch (InvoiceCalculationException $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'items']);
        }

        /* Assess the tax rate (unless disabled) */

        $shouldAssessTaxes = true;
        if (isset($model->calculate_taxes)) {
            $shouldAssessTaxes = $model->calculate_taxes;
            unset($model->calculate_taxes);
        }

        if ($shouldAssessTaxes) {
            $salesTaxInvoice = $model->toSalesTaxDocument($calculatedInvoice);

            try {
                $assessedTaxes = $model->getSalesTaxCalculator()->assess($salesTaxInvoice);
            } catch (TaxCalculationException $e) {
                throw new ListenerException($e->getMessage(), ['field' => 'taxes']);
            }

            // If tax was assessed then the invoice needs to be recalculated
            if (count($assessedTaxes) > 0) {
                $calculatedInvoice->taxes = array_merge(
                    $calculatedInvoice->taxes,
                    $assessedTaxes
                );

                // This prevents duplicates of the same tax rate
                $calculatedInvoice->taxes = Tax::expandList($calculatedInvoice->taxes);

                try {
                    InvoiceCalculator::calculateInvoice($calculatedInvoice);
                } catch (InvoiceCalculationException $e) {
                    throw new ListenerException($e->getMessage(), ['field' => 'taxes']);
                }
            }
        }

        /* Save the results of the invoice calculation */

        // denormalize the money amounts because that is how we store them
        // due to legacy reasons
        $calculatedInvoice->denormalize()->finalize();
        $model->subtotal = $calculatedInvoice->subtotal;
        $model->total = $calculatedInvoice->total;

        // ensure the line items get saved
        $model->_saveLineItems = $calculatedInvoice->items;
        unset($model->items);

        // ensure the applied rates get saved
        $model->_saveDiscounts = $calculatedInvoice->discounts;
        $model->_saveTaxes = $calculatedInvoice->taxes;
        $model->_saveShipping = $calculatedInvoice->shipping;
        unset($model->discounts);
        unset($model->taxes);
        unset($model->shipping);

        // send the invoice after being created
        if (isset($model->send) && $model->send) {
            $model->_send = $model->send;
            unset($model->send);
        }

        // save any file attachments
        if (isset($model->attachments) && is_array($model->attachments)) { /* @phpstan-ignore-line */
            $model->_saveAttachments = $model->attachments;
            unset($model->attachments);

            if (isset($model->pdf_attachment)) {
                $model->_pdfAttachment = $model->pdf_attachment;
                unset($model->pdf_attachment);
            }
        }

        // check if the document was issued
        $model->_wasIssued = !$model->draft;
    }

    /**
     * Is document editable.
     */
    public function isEditable(): bool
    {
        // cannot edit a voided invoice
        if ($this->ignoreUnsaved()->voided && (!$this->dirty('voided') || $this->voided)) {
            $this->getErrors()->add('Your changes cannot be saved because this document is voided.', ['field' => 'voided']);

            return false;
        }

        // cannot edit a closed invoice
        if (!$this->_skipClosedCheck && $this->ignoreUnsaved()->closed && (!$this->dirty('closed') || $this->closed)) {
            $this->getErrors()->add('Your changes cannot be saved because this document is closed. Please re-open the document to make any changes.', ['field' => 'closed']);

            return false;
        }

        return true;
    }

    public static function isIssueCreate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        // is Issue
        if (!$model->draft) {
            $requester = ACLModelRequester::get();
            if ($requester instanceof Member && !$requester->allowed($model->getPermissionName().'.issue')) {
                throw new ListenerException('no_permission');
            }
        }
    }

    public static function isIssueUpdate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        // is Issue
        if ($model->dirty('draft') && !$model->draft) {
            $requester = ACLModelRequester::get();
            if ($requester instanceof Member && !$requester->allowed($model->getPermissionName().'.issue')) {
                throw new ListenerException('no_permission');
            }
        }
    }

    public static function beforeUpdate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if (!$model->isEditable()) {
            // save any file attachments
            if (isset($model->attachments) && is_array($model->attachments)) { /* @phpstan-ignore-line */
                $model->_saveAttachments = $model->attachments;

                if (isset($model->pdf_attachment)) {
                    $model->_pdfAttachment = $model->pdf_attachment;
                }
            }

            $model->saveAttachments(true);
            throw new ListenerException(); // message not needed since it was already set by isEditable()
        }

        // check the number is unique
        $documentNumber = trim(strtolower((string) $model->number));
        $previousNumber = trim(strtolower((string) $model->ignoreUnsaved()->number));
        if ($documentNumber && $documentNumber != $previousNumber) {
            if (!$model->getNumberingSequence()->isUnique($model->number)) {
                $name = strtolower(Inflector::get()->titleize(static::modelName()));
                throw new ListenerException('The given '.$name.' number has already been taken: '.$model->number, ['field' => 'number']);
            }
        }

        // validate the currency is not being cleared
        if (!$model->currency) {
            throw new ListenerException('The currency cannot be unset.', ['field' => 'currency']);
        }

        // cannot go from published to draft
        if ($model->dirty('draft', true) && $model->draft) {
            throw new ListenerException('Cannot save as a draft because the document has already been issued.', ['field' => 'draft']);
        }

        // check if document was issued
        $model->_wasIssued = false;
        if ($model->dirty('draft', true) && !$model->draft) {
            // issuing the document!
            $model->_wasIssued = true;
        }

        // check to see if the document needs to be recalculated
        $recalculate = false;
        foreach (self::$recalculateProperties as $prop) {
            if ($model->dirty($prop)) {
                $recalculate = true;
            }
        }

        if ($recalculate) {
            if (!$model->dirty('items')) {
                $model->_noItemSave = true;
                $model->items = $model->items();
            }

            // handle flat amounts, i.e. 'tax' => 12.34
            foreach (['discount' => 'discounts', 'tax' => 'taxes', 'shipping' => 'shipping'] as $amountKey => $arrayKey) {
                if (isset($model->$amountKey) && is_numeric($model->$amountKey)) {
                    $model->$arrayKey = [['amount' => $model->$amountKey]];
                    unset($model->$amountKey);
                }
            }

            foreach (['discounts', 'taxes', 'shipping'] as $type) {
                if (!$model->dirty($type)) {
                    $model->$type = $model->$type();
                }
            }

            try {
                $calculatedInvoice = InvoiceCalculator::prepare($model->currency, $model->items, $model->discounts, $model->taxes, $model->shipping);
                InvoiceCalculator::calculateInvoice($calculatedInvoice);
            } catch (InvoiceCalculationException $e) {
                throw new ListenerException($e->getMessage(), ['field' => 'items']);
            }

            /* Assess the tax rate (when requested) */

            $shouldAssessTaxes = false;
            if (isset($model->calculate_taxes)) {
                $shouldAssessTaxes = $model->calculate_taxes;
                unset($model->calculate_taxes);
            }

            if ($shouldAssessTaxes) {
                $salesTaxInvoice = $model->toSalesTaxDocument($calculatedInvoice);

                try {
                    $assessedTaxes = $model->getSalesTaxCalculator()->adjust($salesTaxInvoice);
                } catch (TaxCalculationException $e) {
                    throw new ListenerException($e->getMessage(), ['field' => 'taxes']);
                }

                // If tax was assessed then the invoice needs to be recalculated.
                // When doing an adjustment assessed taxes replace any existing taxes
                if (count($assessedTaxes) > 0) {
                    $calculatedInvoice->taxes = $assessedTaxes;

                    // This prevents duplicates of the same tax rate
                    $calculatedInvoice->taxes = Tax::expandList($calculatedInvoice->taxes);

                    try {
                        InvoiceCalculator::calculateInvoice($calculatedInvoice);
                    } catch (InvoiceCalculationException $e) {
                        throw new ListenerException($e->getMessage(), ['field' => 'taxes']);
                    }
                }
            }

            // denormalize the money amounts because that is how we store them
            // due to legacy reasons
            $calculatedInvoice->denormalize()->finalize();

            // ensure the line items get saved
            if (!$model->_noItemSave) {
                $model->_saveLineItems = $calculatedInvoice->items;
                unset($model->items);
            }

            // ensure the applied rates get saved
            $model->_saveDiscounts = $calculatedInvoice->discounts;
            $model->_saveTaxes = $calculatedInvoice->taxes;
            $model->_saveShipping = $calculatedInvoice->shipping;

            $model->subtotal = $calculatedInvoice->subtotal;
            $model->total = $calculatedInvoice->total;
        } else {
            foreach (['subtotal', 'total'] as $k) {
                if ($model->dirty($k)) {
                    unset($model->$k);
                }
            }
        }

        // save any file attachments
        if (isset($model->attachments) && is_array($model->attachments)) { /* @phpstan-ignore-line */
            $model->_saveAttachments = $model->attachments;
            unset($model->attachments);

            if (isset($model->pdf_attachment)) {
                $model->_pdfAttachment = $model->pdf_attachment;
                unset($model->pdf_attachment);
            }

            // ensure something gets saved or else
            // the update will appear to fail
            if (!$model->dirty()) {
                $model->currency = $model->currency;
            }
        }
    }

    /**
     * Blocks a document from being deleted if it cannot be voided.
     */
    public static function checkDelete(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        try {
            $model->checkIfVoidable();
        } catch (ModelException $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'voided']);
        }
    }

    /**
     * Saves the relationships on the document.
     */
    public static function saveModelRelationships(AbstractEvent $event, string $eventName): void
    {
        /** @var self $document */
        $document = $event->getModel();
        $isUpdate = ModelUpdated::getName() == $eventName;

        // BUG refresh() has to be present here because for some reason
        // the model properties like $this->tenant_id are missing at this stage
        if (!$isUpdate) {
            $document->refresh();
        }

        $document->saveLineItems($isUpdate);
        $document->_noItemSave = false;

        $document->saveDiscounts($isUpdate);
        $document->saveTaxes($isUpdate);
        $document->saveShipping($isUpdate);
        $document->saveAttachments($isUpdate);

        $document->_skipClosedCheck = false;
    }

    public static function sendAfterIssue(AbstractEvent $event): void
    {
        /** @var self $document */
        $document = $event->getModel();

        if ($document->_send) {
            $document->_send = false;
            $emailTemplate = (new DocumentEmailTemplateFactory())->get($document);
            EmailSpoolFacade::get()->spoolDocument($document, $emailTemplate, [], false);
        }

        // Auto-send network documents
        if (!$document->draft && !$document->network_document_id && $document->customer()->network_connection_id) {
            QueueFacade::get()->enqueue(
                SendNetworkDocumentQueueJob::class, [
                    'tenant_id' => $document->tenant_id,
                    $document->object => $document->id,
                ], QueueServiceLevel::Batch
            );
        }
    }

    /**
     * Clears the company currency cache when a new currency is encountered.
     */
    public static function bustCurrenciesCache(AbstractEvent $event): void
    {
        /** @var self $document */
        $document = $event->getModel();
        $company = $document->tenant();
        if (!in_array($document->currency, $company->getCurrencies())) {
            $company->clearCurrenciesCache();
        }
    }

    /**
     * Voids any assessed taxes before voiding or deleting the invoice.
     */
    public static function voidAssessedTaxes(AbstractEvent $event, string $eventName): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // When an update is happening, check if the update
        // is because the document is being voided.
        if (ModelUpdating::getName() == $eventName) {
            if (!$model->voided) {
                return;
            }
        }

        $taxes = $model->taxes;
        if (0 == count($taxes)) {
            return;
        }

        try {
            $calculatedInvoice = InvoiceCalculator::prepare($model->currency, $model->items, $model->discounts, $taxes, $model->shipping);
            InvoiceCalculator::calculateInvoice($calculatedInvoice);
        } catch (InvoiceCalculationException $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'items']);
        }

        $salesTaxInvoice = $model->toSalesTaxDocument($calculatedInvoice);

        try {
            $model->getSalesTaxCalculator()->void($salesTaxInvoice);
        } catch (TaxCalculationException $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'taxes']);
        }
    }

    public function ignoreUnsaved(): static
    {
        $this->ignoreUnsavedArray = true;

        return parent::ignoreUnsaved();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $this->toArrayHook($result, [], [], []);

        return $result;
    }

    public function toArrayHook(array &$result, array $exclude, array $include, array $expand): void
    {
        if ($this->_noArrayHook) {
            $this->_noArrayHook = false;

            return;
        }

        // line items
        if (!isset($exclude['items'])) {
            // This must be called again because of the hook
            if ($this->ignoreUnsavedArray) {
                $this->ignoreUnsaved();
            }

            $expandCatalogItem = (bool) array_value($expand, 'items.catalog_item');
            $result['items'] = $this->items(false, $expandCatalogItem);
        }

        // deprecated
        if (isset($exclude['rates'])) {
            $exclude['discounts'] = true;
            $exclude['taxes'] = true;
            $exclude['shipping'] = true;
        }

        // discounts
        if (!isset($exclude['discounts'])) {
            // This must be called again because of the hook
            if ($this->ignoreUnsavedArray) {
                $this->ignoreUnsaved();
            }

            $result['discounts'] = $this->discounts();
        }

        // taxes
        if (!isset($exclude['taxes'])) {
            // This must be called again because of the hook
            if ($this->ignoreUnsavedArray) {
                $this->ignoreUnsaved();
            }

            $result['taxes'] = $this->taxes();
        }

        // shipping
        if (!isset($exclude['shipping'])) {
            // This must be called again because of the hook
            if ($this->ignoreUnsavedArray) {
                $this->ignoreUnsaved();
            }

            $result['shipping'] = $this->shipping();
        }

        // customer name
        if (isset($include['customerName'])) {
            // This must be called again because of the hook
            if ($this->ignoreUnsavedArray) {
                $this->ignoreUnsaved();
            }

            $result['customerName'] = $this->customer()->name;
        }

        $this->ignoreUnsavedArray = false;
    }

    //
    // Mutators
    //

    /**
     * Sets the currency.
     */
    protected function setCurrencyValue(string $currency): string
    {
        return strtolower($currency);
    }

    //
    // Accessors
    //

    /**
     * Generates the URL for the client view.
     */
    abstract protected function getUrlValue(): ?string;

    /**
     * Generates the URL to download as PDF.
     */
    abstract protected function getPdfUrlValue(): ?string;

    /**
     * Generates the URL to download as CSV.
     */
    abstract protected function getCsvUrlValue(): ?string;

    /**
     * Generates the URL to download as XML.
     */
    abstract protected function getXmlUrlValue(): ?string;

    /**
     * Gets any attached shipping details.
     */
    abstract protected function getShipToValue(mixed $shipTo): ?ShippingDetail;

    //
    // Validators
    //

    /**
     * Validates the value is not negative.
     */
    public static function notNegative(float $value): bool
    {
        return $value >= 0;
    }

    //
    // Getters
    //

    /**
     * @throws InvoiceCalculationException
     */
    public function getCalculatedInvoice(): CalculatedInvoice
    {
        $calculatedInvoice = InvoiceCalculator::prepare($this->currency, $this->items(), $this->discounts(), $this->taxes(), $this->shipping());
        InvoiceCalculator::calculateInvoice($calculatedInvoice);

        return $calculatedInvoice;
    }

    public function getSalesTaxCalculator(): TaxCalculatorInterface
    {
        if (!isset($this->taxCalculator)) {
            $this->taxCalculator = TaxCalculatorFactoryFacade::get()->get($this->tenant());
        }

        return $this->taxCalculator;
    }

    /**
     * Converts this document into a sales tax document.
     */
    abstract public function toSalesTaxDocument(CalculatedInvoice $calculatedInvoice, bool $preview = false): SalesTaxInvoice;

    /**
     * Gets the shipping address for tax calculation purposes.
     */
    public function getSalesTaxAddress(): Address
    {
        $formatter = new AddressFormatter();

        // look for a shipping address
        if ($shippingDetail = $this->ship_to) {
            return $formatter->setFrom($shippingDetail)->buildAddress(false);
        }

        $formatter->setFrom($this->customer());

        return $formatter->buildAddress(false);
    }

    public function getTotal(): Money
    {
        return Money::fromDecimal($this->currency, $this->total);
    }

    /**
     * Checks whether the document can be voided.
     *
     * @throws ModelException
     */
    abstract protected function checkIfVoidable(): void;

    //
    // Setters
    //

    /**
     * Recalculates the document by loading the line items
     * and applied rates from the database.
     */
    public function recalculate(): bool
    {
        $this->_noItemSave = true;

        $params = [
            'discounts' => $this->discounts(true),
            'taxes' => $this->taxes(true),
            'shipping' => $this->shipping(true),
            'items' => $this->items(true),
        ];

        return $this->set($params);
    }

    /**
     * Triggers a status update on this document.
     *
     * @return bool true when the status was updated
     */
    public function updateStatus(): bool
    {
        $before = $this->status;

        // set a non-property to ensure the update happens as the
        // status will be calculated in the model.updating hook
        $this->_update = true; /* @phpstan-ignore-line */

        $this->skipReconciliation();
        $this->skipClosedCheck();
        $this->save();

        return $before != $this->status;
    }

    /**
     * Voids the document. This operation is irreversible.
     *
     * @throws ModelException
     */
    public function void(bool $skipVoidableCheck = false): void
    {
        if ($this->voided) {
            throw new ModelException('This document has already been voided.');
        }

        $requester = ACLModelRequester::get();
        if ($requester instanceof Member && !$requester->allowed($this->getPermissionName().'.void')) {
            throw new ModelException('You do not have permissions to perform the action.');
        }

        if (!$skipVoidableCheck) {
            $this->checkIfVoidable();
        }

        if ($this->closed) {
            $this->skipClosedCheck();
        }

        $this->voided = true;
        $this->date_voided = time();
        $this->saveOrFail();
    }

    public function setTaxCalculator(TaxCalculatorInterface $calculator): void
    {
        $this->taxCalculator = $calculator;
    }

    /**
     * Gets the merchant account to process payment against.
     */
    public function getMerchantAccount(PaymentMethod $method): ?MerchantAccount
    {
        return null;
    }

    /**
     * Get amount of money to be processed.
     */
    abstract public function amountToProcess(): float;

    public function getThreadName(): string
    {
        return $this->number;
    }

    abstract protected function getPermissionName(): string;

    public function getAccountingObjectReference(): InvoicedObjectReference
    {
        return new InvoicedObjectReference($this->object, (string) $this->id(), $this->number);
    }

    public function getDateVoided(): ?string
    {
        return $this->date_voided ? CarbonImmutable::createFromTimestamp($this->date_voided)->format(DateTimeInterface::ATOM) : null;
    }
}
