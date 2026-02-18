<?php

namespace App\CashApplication\Models;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Traits\HasCustomerTrait;
use App\CashApplication\Libs\CreditBalanceHistory;
use App\CashApplication\Libs\TransactionTreeIterator;
use App\CashApplication\Pdf\TransactionPdf;
use App\CashApplication\Pdf\TransactionPdfVariables;
use App\CashApplication\ValueObjects\TransactionTree;
use App\Companies\Traits\MoneyTrait;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\Currencies;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\LoggerFacade;
use App\Core\Multitenant\Models\HasCustomerRestrictionsTrait;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Event\ModelDeleting;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Event\ModelUpdating;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Pdf\PdfDocumentInterface;
use App\Core\Utils\AppUrl;
use App\Core\Utils\ModelNormalizer;
use App\Core\Utils\Traits\HasClientIdTrait;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventModelTrait;
use App\Integrations\AccountingSync\Models\AccountingWritableModel;
use App\Integrations\AccountingSync\ValueObjects\InvoicedObjectReference;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Libs\CustomFieldRepository;
use App\Metadata\Traits\MetadataTrait;
use App\PaymentProcessing\Interfaces\HasPaymentSourceInterface;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Traits\HasPaymentSourceTrait;
use App\Themes\Interfaces\PdfBuilderInterface;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Interfaces\ThemeableInterface;
use App\Themes\Traits\ThemeableTrait;

/**
 * A transaction represents a transfer of value between a customer and merchant.
 * Transactions can go either direction, for example, a charge could refer to
 * money paid by the customer to the merchant. In contrast, a refund would reference
 * money paid back to the customer (from the original charge).
 * The supported transaction types are:
 * - charge
 * - payment
 * - refund
 * - adjustment.
 *
 * @property int          $id
 * @property string       $type
 * @property int          $date
 * @property string       $status
 * @property string       $method
 * @property int|null     $invoice
 * @property int|null     $credit_note
 * @property int|null     $credit_note_id
 * @property int|null     $estimate
 * @property int|null     $estimate_id
 * @property string       $currency
 * @property float        $amount
 * @property string|null  $notes
 * @property string|null  $gateway
 * @property string|null  $gateway_id
 * @property string|null  $failure_reason
 * @property int|null     $parent_transaction
 * @property int          $last_status_check
 * @property Payment|null $payment
 * @property int|null     $payment_id
 * @property string|null  $pdf_url
 * @property string|null  $invoice_number
 * @property string|null  $estimate_number
 * @property string|null  $credit_note_number
 * @property array        $children
 * @property bool         $sent
 */
class Transaction extends AccountingWritableModel implements EventObjectInterface, MetadataModelInterface, PdfDocumentInterface, ThemeableInterface, HasPaymentSourceInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;
    use HasClientIdTrait;
    use HasCustomerTrait;
    use HasPaymentSourceTrait;
    use HasCustomerRestrictionsTrait;
    use MetadataTrait;
    use MoneyTrait;
    use ThemeableTrait;

    // transaction types
    const TYPE_CHARGE = 'charge';
    const TYPE_PAYMENT = 'payment';
    const TYPE_REFUND = 'refund';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_DOCUMENT_ADJUSTMENT = 'document_adjustment';

    // transaction statuses
    const STATUS_SUCCEEDED = 'succeeded';
    const STATUS_PENDING = 'pending';
    const STATUS_FAILED = 'failed';

    /**
     * Properties that are allowed to be modified
     * on charge transactions after create.
     */
    private static array $chargeAllowedEditProperties = [
        'status',
        'notes',
        'sent',
        'metadata',
        'updated_at',
    ];

    private Money $_amountDelta;
    private TransactionTree $_tree;
    private ?CreditBalanceHistory $_creditBalanceHist = null;
    private array $_cachedBreakdown;

    protected static function getProperties(): array
    {
        return [
            'date' => new Property(
                type: Type::DATE_UNIX,
                required: true,
                validate: 'timestamp',
                default: 'now',
            ),
            'type' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['enum', 'choices' => ['charge', 'payment', 'refund', 'adjustment', 'document_adjustment']],
                default: Transaction::TYPE_PAYMENT,
            ),
            'status' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['succeeded', 'pending', 'failed']],
                default: Transaction::STATUS_SUCCEEDED,
            ),
            'method' => new Property(
                required: true,
                default: PaymentMethod::OTHER,
            ),
            'currency' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
                validate: ['callable', 'fn' => [self::class, 'notZero']],
            ),
            'notes' => new Property(
                null: true,
            ),
            'gateway' => new Property(
                null: true,
            ),
            'gateway_id' => new Property(
                null: true,
            ),
            'payment_source_id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'payment_source_type' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                validate: ['enum', 'choices' => ['card', 'bank_account']],
                in_array: false,
            ),
            'parent_transaction' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: self::class,
            ),
            'sent' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
            'failure_reason' => new Property(
                null: true,
                in_array: false,
            ),

            /* Computed properties */

            'last_status_check' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),

            /* Attached Documents */

            'invoice' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: Invoice::class,
                mutable: Property::MUTABLE_CREATE_ONLY,
            ),
            'credit_note_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'credit_note' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                relation: CreditNote::class,
            ),
            'payment' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                belongs_to: Payment::class,
            ),
            'estimate_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'estimate' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                relation: Estimate::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'verifyInvoice']);
        self::creating([self::class, 'verifyCreditNote']);
        self::creating([self::class, 'verifyEstimate']);
        self::creating([self::class, 'verifyCustomer']);
        self::creating([self::class, 'verifyParentTransaction']);
        self::creating([self::class, 'inheritCurrency']);
        self::creating([self::class, 'checkAmount']);
        self::creating([self::class, 'computeDeltaCreate']);
        self::creating([self::class, 'validateType']);
        self::saving([self::class, 'validateCreditBalanceHistory']);
        self::saved([self::class, 'applyToInvoice']);
        self::saved([self::class, 'applyToCreditNote']);
        self::saved([self::class, 'applyToEstimate']);
        self::saved([self::class, 'writeCreditBalanceHistory']);

        self::updating([self::class, 'computeDeltaUpdate']);
        self::updating([static::class, 'protectCustomer']);
        self::updating([self::class, 'cannotEditChargeTransactions']);

        self::deleting([self::class, 'computeDeltaDelete']);
        self::deleting([self::class, 'validateCreditBalanceHistory']);
        self::deleted([self::class, 'deleteChildren']);
        self::deleted([self::class, 'applyToInvoice']);
        self::deleted([self::class, 'applyToCreditNote']);
        self::deleted([self::class, 'applyToEstimate']);
        self::deleted([self::class, 'writeCreditBalanceHistory']);

        self::updating([self::class, 'beforeUpdate'], -512);

        parent::initialize();
    }

    protected function getMassAssignmentBlocked(): ?array
    {
        return ['payment_source_id', 'payment_source_type'];
    }

    //
    // Hooks
    //

    /**
     * Verifies the invoice relationship when creating.
     */
    public static function verifyInvoice(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // verify the invoice
        $iid = $model->invoice;
        if (!$iid) {
            return;
        }

        $invoice = $model->invoice();
        if (!$invoice) {
            throw new ListenerException("No such invoice: $iid", ['field' => 'invoice']);
        }

        // use customer from invoice if not already given
        if (!$model->customer) {
            /** @var Customer $customer */
            $customer = $invoice->customer();
            $model->setCustomer($customer);
        }

        // inherit metadata from invoice (when enabled)
        $company = $model->tenant();
        if ($company->accounts_receivable_settings->transactions_inherit_invoice_metadata) {
            $invoiceMetadata = [];
            $repository = new CustomFieldRepository($company);
            foreach ($invoice->metadata as $k => $v) { /* @phpstan-ignore-line */
                $customField = $repository->getCustomField($model->object, $k);
                if ($customField) {
                    $invoiceMetadata[$k] = $v;
                }
            }

            $model->metadata = (object) array_replace(
                $invoiceMetadata,
                (array) $model->metadata
            );
        }
    }

    /**
     * Verifies the credit note relationship when creating.
     */
    public static function verifyCreditNote(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->dirty('credit_note')) {
            unset($model->credit_note);
        }

        // verify the credit note
        $cid = $model->credit_note_id;
        if (!$cid) {
            return;
        }

        $creditNote = $model->creditNote();
        if (!$creditNote) {
            throw new ListenerException("No such credit note: $cid", ['field' => 'credit_note']);
        }

        // use customer from credit note if not already given
        if (!$model->customer) {
            /** @var Customer $customer */
            $customer = $creditNote->customer();
            $model->setCustomer($customer);
        }

        // use balance from credit note if not already given
        if (!$model->amount) {
            $model->type = Transaction::TYPE_ADJUSTMENT;
            $model->amount = -$creditNote->balance;
        }
    }

    public static function verifyEstimate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->dirty('estimate')) {
            unset($model->estimate);
        }

        // verify the estimate
        $eid = $model->estimate_id;
        if (!$eid) {
            return;
        }

        $estimate = $model->estimate();
        if (!$estimate) {
            throw new ListenerException("No such estimate: $eid", ['field' => 'estimate']);
        }

        // use customer from estimate if not already given
        if (!$model->customer) {
            $model->setCustomer($estimate->customer());
        }

        // use total from estimate if not already given
        if (!$model->amount) {
            $model->amount = $estimate->total;
        }
    }

    /**
     * Verifies the parent transaction relationship when creating.
     */
    public static function verifyParentTransaction(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $tid = $model->parent_transaction;
        if (!$tid) {
            return;
        }

        if (!$model->parentTransaction()) {
            throw new ListenerException("No such transaction: $tid", ['field' => 'parent_transaction']);
        }
    }

    /**
     * Use currency from invoice, credit note, or company when not
     * already provided.
     */
    public static function inheritCurrency(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if (!$model->currency && $invoice = $model->invoice()) {
            $model->currency = $invoice->currency;
        }

        if (!$model->currency && $creditNote = $model->creditNote()) {
            $model->currency = $creditNote->currency;
        }

        if (!$model->currency) {
            $model->currency = $model->tenant()->currency;
        }
    }

    /**
     * Checks that the transaction amount is valid.
     */
    public static function checkAmount(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // round amount to currency precision
        $model->amount = MoneyFormatter::get()->round($model->currency, $model->amount ?? 0);
        if (in_array($model->type, [Transaction::TYPE_PAYMENT, Transaction::TYPE_CHARGE])) {
            if ($model->amount < 0) {
                throw new ListenerException('Creating negative payments is not allowed. Please use a positive amount or create a `refund` transaction instead.', ['field' => 'amount']);
            }
        } elseif (Transaction::TYPE_REFUND == $model->type) {
            if ($model->amount < 0) {
                throw new ListenerException('Creating negative refunds is not allowed. Please use a positive amount or create a `payment` transaction instead.', ['field' => 'amount']);
            }
        }
    }

    /**
     * Calculates the delta caused by this change.
     */
    public static function computeDeltaCreate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // calculate the delta we need to apply
        if (Transaction::STATUS_SUCCEEDED === $model->status) {
            $model->_amountDelta = $model->transactionAmount();

            if (Transaction::TYPE_REFUND == $model->type) {
                $model->_amountDelta = $model->_amountDelta->negated();
            }
        } else {
            $model->_amountDelta = new Money($model->currency, 0);
        }

        $delta = $model->_amountDelta;
        // negate if credit note application
        if ($model->creditNote() && Transaction::TYPE_ADJUSTMENT == $model->type && PaymentMethod::BALANCE != $model->method) {
            $delta = $model->_amountDelta->negated();
        }

        // invoices
        if ($invoice = $model->invoice()) {
            // verify the currency matches the invoice currency
            if ($invoice->currency != $model->currency) {
                throw new ListenerException("The currency on this transaction ({$model->currency}) must match the invoice currency ({$invoice->currency}).", ['field' => 'currency']);
            }
        }

        // credit notes
        if ($creditNote = $model->creditNote()) {
            // verify the currency matches the credit note currency
            if ($creditNote->currency != $model->currency) {
                throw new ListenerException("The currency on this transaction ({$model->currency}) must match the credit note currency ({$creditNote->currency}).", ['field' => 'currency']);
            }
        }
    }

    /**
     * Validates the model type.
     */
    public static function validateType(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // adjustments always use balance as the method, except for credit notes applied to an invoice
        if (Transaction::TYPE_ADJUSTMENT == $model->type && !$model->invoice) {
            $model->method = PaymentMethod::BALANCE;
        }

        if (in_array($model->type, [Transaction::TYPE_CHARGE, Transaction::TYPE_PAYMENT, Transaction::TYPE_REFUND]) && $model->credit_note) {
            throw new ListenerException('Only adjustments can be applied to credit notes.', ['field' => 'credit_note']);
        }

        if ('now' == $model->date) {
            $model->date = time();
        }
    }

    /**
     * Validates how writing this transaction would affect
     * the credit balance history.
     */
    public static function validateCreditBalanceHistory(AbstractEvent $event, string $eventName): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if (PaymentMethod::BALANCE != $model->method || !in_array($model->type, [Transaction::TYPE_ADJUSTMENT, Transaction::TYPE_CHARGE])) {
            return;
        }

        $model->_creditBalanceHist = new CreditBalanceHistory($model->customer(), $model->currency, $model->date);

        // determine what type of operation this is
        // i.e. create, delete
        if (ModelCreating::getName() === $eventName) {
            $model->_creditBalanceHist->addTransaction($model);
        } elseif (ModelUpdating::getName() === $eventName) {
            $date = $model->date;
            $amount = $model->amount;
            $model->_creditBalanceHist->changeTransaction((int) $model->id(), $date, $amount);
        } elseif (ModelDeleting::getName() === $eventName) {
            $model->_creditBalanceHist->deleteTransaction((int) $model->id());
        }

        // prevent overspending
        $overspend = $model->_creditBalanceHist->getOverspend();
        if (null !== $overspend) {
            $balance = $model->currencyFormat($overspend->balance);
            throw new ListenerException('Could not write this change because it caused the customer\'s credit balance to become '.$balance.' on '.date('M j, Y', $overspend->timestamp), ['field' => 'amount', 'reason' => 'credit_balance']);
        }
    }

    /**
     * Protects non-balance charge transactions from being edited.
     */
    public static function cannotEditChargeTransactions(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (Transaction::TYPE_CHARGE != $model->type || PaymentMethod::BALANCE == $model->method) {
            return;
        }

        $after = $model->toArray();
        $before = $model->ignoreUnsaved()->toArray();

        foreach (self::$chargeAllowedEditProperties as $k) {
            unset($before[$k]);
            unset($after[$k]);
        }

        foreach ($after as $k => $v) {
            if ($v != $before[$k]) {
                throw new ListenerException("The `$k` property cannot be modified on this transaction. Only these properties can be modified on charge transactions: ".implode(', ', self::$chargeAllowedEditProperties), ['field' => $k]);
            }
        }
    }

    public static function beforeUpdate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // calculate the delta we need to apply
        if ($model->dirty('amount')) {
            // round amount to currency precision
            $model->amount = MoneyFormatter::get()->round($model->currency, $model->amount);

            // refund amounts cannot be modified
            if (Transaction::TYPE_REFUND == $model->ignoreUnsaved()->type) {
                throw new ListenerException('Refund amount cannot be changed.', ['field' => 'amount']);
            }
        }

        $status = $model->status;

        // handle going from failed/pending -> succeeded
        if (Transaction::STATUS_SUCCEEDED === $status) {
            // The delta for this update starts with the
            // latest amount for the transaction
            $amount = $model->amount;

            // add the previous amount of the transaction
            // if it was already succeeded
            if ($model->ignoreUnsaved()->status === $status) {
                $amount -= $model->ignoreUnsaved()->amount;
            }

            $model->_amountDelta = Money::fromDecimal($model->currency, $amount);

            // handle going from succeeded -> failed/pending
            // NOTE: there would never be a situation where we go
            // back to pending but it's supported for completeness
        } elseif (Transaction::STATUS_SUCCEEDED === $model->ignoreUnsaved()->status) {
            // subtract the previous amount, completely ignoring
            // any delta calculations during this update because
            // they don't matter
            $model->_amountDelta = Money::fromDecimal($model->currency, -$model->ignoreUnsaved()->amount);
        } else {
            $model->_amountDelta = new Money($model->currency, 0);
        }

        $zero = new Money($model->currency, 0);
        if ($model->_amountDelta->greaterThan($zero)) {
            $delta = $model->_amountDelta;

            if ($model->creditNote() && Transaction::TYPE_ADJUSTMENT == $model->type && PaymentMethod::BALANCE != $model->method) {
                $delta = $model->_amountDelta->negated();
            }
        }
    }

    /**
     * Writes credit balance history.
     */
    public static function writeCreditBalanceHistory(AbstractEvent $event, string $eventName): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if (!$model->_creditBalanceHist) {
            return;
        }

        if (ModelCreated::getName() == $eventName) {
            $model->_creditBalanceHist->setUnsavedId((int) $model->id());
        }

        if (!$model->_creditBalanceHist->persist()) {
            LoggerFacade::get()->error('Could not write credit balance history for customer # '.$model->_creditBalanceHist->getCustomer()->id());
        }
        $model->_creditBalanceHist = null;
    }

    public static function computeDeltaUpdate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $creditNote = $model->creditNote();

        if (!$creditNote || Transaction::TYPE_ADJUSTMENT != $model->type || PaymentMethod::BALANCE == $model->method) {
            return;
        }

        $originalAmount = Money::fromDecimal($model->currency, $model->ignoreUnsaved()->amount);
        $newAmount = $model->transactionAmount();

        $model->_amountDelta = $newAmount->subtract($originalAmount);
    }

    /**
     * Calculates the delta caused by this change.
     */
    public static function computeDeltaDelete(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if (Transaction::STATUS_SUCCEEDED === $model->status) {
            $model->_amountDelta = Money::fromDecimal($model->currency, -$model->amount);

            if (Transaction::TYPE_REFUND === $model->type) {
                $model->_amountDelta = Money::fromDecimal($model->currency, $model->amount);
            }
        } else {
            $model->_amountDelta = new Money($model->currency, 0);
        }
    }

    /**
     * Deletes any children transactions.
     */
    public static function deleteChildren(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // Payment object will manage deleting children transactions
        if ($model->payment_id) {
            return;
        }

        $children = self::where('parent_transaction', $model->id())
            ->all();
        foreach ($children as $transaction) {
            $transaction->delete();
        }
    }

    /**
     * Applies the payment delta to the invoice.
     */
    public static function applyToInvoice(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $invoice = $model->invoice();
        if (!$invoice) {
            return;
        }

        // if the amount did not change then trigger an
        // update on the invoice status to account for the
        // transaction's current state
        if ($model->_amountDelta->isZero()) {
            $invoice->updateStatus();

            return;
        }

        try {
            if ($model->creditNote()) {
                $invoice->applyCredit($model->_amountDelta->negated());
            } else {
                $invoice->applyPayment($model->_amountDelta);
            }
        } catch (ModelException) {
            throw new ListenerException('Could not apply payment to invoice: '.$invoice->getErrors(), ['field' => 'invoice']);
        }
    }

    /**
     * Applies the payment delta to an estimate.
     */
    public static function applyToEstimate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $estimate = $model->estimate();
        if (!$estimate) {
            return;
        }

        // if the amount did not change then do nothing
        if ($model->_amountDelta->isZero()) {
            return;
        }

        try {
            $estimate->applyPayment($model->_amountDelta);
        } catch (ModelException) {
            throw new ListenerException('Could not apply payment to estimate: '.$estimate->getErrors(), ['field' => 'estimate']);
        }
    }

    /**
     * Applies the transaction delta to the credit note.
     */
    public static function applyToCreditNote(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $creditNote = $model->creditNote();
        if (!$creditNote) {
            return;
        }

        // if the amount did not change then trigger an
        // update on the credit note status to account for the
        // transaction's current state
        if ($model->_amountDelta->isZero()) {
            $creditNote->updateStatus();

            return;
        }

        try {
            // we negate the amount delta because credits
            // are negative values
            if ($model->invoice()) {
                $creditNote->applyToInvoice($model->_amountDelta->negated());
            } else {
                $creditNote->applyToCreditBalance($model->_amountDelta->negated());
            }
        } catch (ModelException) {
            throw new ListenerException('Could not apply transaction to credit note: '.$creditNote->getErrors(), ['field' => 'credit_note']);
        }
    }

    public function toArrayHook(array &$result, array $exclude, array $include, array $expand): void
    {
        if ($this->_noArrayHook) {
            $this->_noArrayHook = false;

            return;
        }

        // customer name
        if (isset($include['customerName'])) {
            $result['customerName'] = $this->customer()->name;
        }
    }

    //
    // Model Overrides
    //

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['pdf_url'] = $this->pdf_url;
        $paymentSource = $this->payment_source;
        $result['payment_source'] = $paymentSource ? $paymentSource->toArray() : null;
        $result['metadata'] = $this->metadata;

        $this->toArrayHook($result, [], [], []);

        if (Transaction::STATUS_FAILED === $this->status) {
            $result['failure_reason'] = $this->failure_reason;
        }

        return $result;
    }

    //
    // Mutators
    //

    /**
     * Sets the currency property.
     *
     * @param string $currency
     */
    protected function setCurrencyValue($currency): string
    {
        return strtolower($currency);
    }

    /**
     * Sets the credit_note property. Alias for credit_note_id.
     *
     * @param int|null $id
     *
     * @return int|null
     */
    protected function setCreditNoteValue($id)
    {
        $this->credit_note_id = $id;

        return $id;
    }

    /**
     * Sets the estimate property. Alias for estimate_id.
     *
     * @param int|null $id
     *
     * @return int|null
     */
    protected function setEstimateValue($id)
    {
        $this->estimate_id = $id;

        return $id;
    }

    //
    // Accessors
    //

    /**
     * Generates the PDF URL for this transaction.
     */
    protected function getPdfUrlValue(): ?string
    {
        if (Transaction::STATUS_SUCCEEDED !== $this->status) {
            return null;
        }

        // always defer receipt generation to parent transaction
        if ($payment = $this->payment) {
            return $payment->pdf_url;
        }

        if ($parent = $this->parentTransaction()) {
            return $parent->pdf_url;
        }

        // only charges/payments can generate receipts
        if (in_array($this->type, [Transaction::TYPE_CHARGE, Transaction::TYPE_PAYMENT])) {
            return AppUrl::get()->build().'/payments/'.$this->tenant()->identifier.'/'.$this->client_id.'/pdf';
        }

        return null;
    }

    /**
     * Gets the invoice_number property.
     */
    protected function getInvoiceNumberValue(): ?string
    {
        $invoice = $this->invoice();

        return $invoice ? $invoice->number : null;
    }

    /**
     * Gets the estimate number property.
     */
    protected function getEstimateNumberValue(): ?string
    {
        $estimate = $this->estimate();

        return $estimate ? $estimate->number : null;
    }

    /**
     * Gets the credit note number property.
     */
    protected function getCreditNoteNumberValue(): ?string
    {
        $creditNote = $this->creditNote();

        return $creditNote ? $creditNote->number : null;
    }

    /**
     * Gets the document number property.
     */
    protected function getDocumentNumberValue(): ?string
    {
        if ($this->invoice) {
            return $this->invoice_number;
        }
        if ($this->estimate) {
            return $this->estimate_number;
        }
        if ($this->credit_note) {
            return $this->credit_note_number;
        }

        return null;
    }

    /**
     * Gets the children property.
     */
    protected function getChildrenValue(): array
    {
        return $this->tree()->toArray();
    }

    /**
     * Gets the credit_note property. Alias for credit_note_id.
     */
    protected function getCreditNoteValue(): ?int
    {
        return $this->credit_note_id;
    }

    /**
     * Gets the estimate property. Alias for estimate_id.
     */
    protected function getEstimateValue(): ?int
    {
        return $this->estimate_id;
    }

    //
    // Relationships
    //

    /**
     * Sets the associated invoice.
     */
    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice = (int) $invoice->id();
        $this->setRelation('invoice', $invoice);
    }

    /**
     * Sets the associated credit note.
     */
    public function setCreditNote(CreditNote $creditNote): void
    {
        $this->credit_note_id = (int) $creditNote->id();
        $this->setRelation('credit_note', $creditNote);
    }

    /**
     * Sets the associated estimate.
     */
    public function setEstimate(Estimate $estimate): void
    {
        $this->estimate_id = (int) $estimate->id();
        $this->setRelation('estimate', $estimate);
    }

    /**
     * Sets the associated parent transaction.
     */
    public function setParentTransaction(Transaction $transaction): void
    {
        $this->parent_transaction = (int) $transaction->id();
        $this->setRelation('parent_transaction', $transaction);
    }

    /**
     * Gets the associated invoice.
     */
    public function invoice(): ?Invoice
    {
        return $this->relation('invoice');
    }

    /**
     * Gets the associated credit note.
     */
    public function creditNote(): ?CreditNote
    {
        return $this->relation('credit_note');
    }

    /**
     * Gets the associated estimate.
     */
    public function estimate(): ?Estimate
    {
        return $this->relation('estimate');
    }

    /**
     * Gets the associated payment.
     */
    public function payment(): ?Payment
    {
        return $this->relation('payment');
    }

    /**
     * Gets the associated parent transaction.
     */
    public function parentTransaction(): ?Transaction
    {
        return $this->relation('parent_transaction');
    }

    /**
     * Gets the method used to make this payment.
     */
    public function getMethod(): PaymentMethod
    {
        $method = new PaymentMethod(['tenant_id' => $this->tenant_id, 'id' => $this->method]);
        $method->gateway = $this->gateway;

        return $method;
    }

    //
    // Getters
    //

    /**
     * Gets the money formatting options for this object.
     */
    public function moneyFormat(): array
    {
        return $this->customer()->moneyFormat();
    }

    /**
     * Gets a money object representing just the amount of this transaction.
     * Does not consider sub-transactions or other associated transactions.
     */
    public function transactionAmount(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount ?? 0);
    }

    /**
     * Gets the gross amount for charge and payment transactions.
     * Refunds are excluded.
     *
     * @throws \Exception when called on a transaction that is not a charge or payment
     */
    public function paymentAmount(): Money
    {
        if ($payment = $this->payment) {
            return $payment->getAmount();
        }

        if (!in_array($this->type, [Transaction::TYPE_CHARGE, Transaction::TYPE_PAYMENT])) {
            throw new \Exception('Payment amount only available on charges and payments.');
        }

        // This calculation is used for legacy payment transactions that do not
        // have a payment object associated.
        $paid = new Money($this->currency, 0);
        foreach (TransactionTreeIterator::make($this) as $transaction) {
            // if the node is a charge or payment then add to total
            if (in_array($transaction->type, [Transaction::TYPE_CHARGE, Transaction::TYPE_PAYMENT])) {
                $paid = $paid->add($transaction->transactionAmount());
            }
        }

        return $paid;
    }

    /**
     * Gets the amount refunded for this transaction.
     *
     * @throws \Exception when called on a transaction that is not a charge or payment
     */
    public function amountRefunded(): Money
    {
        if (!in_array($this->type, [Transaction::TYPE_CHARGE, Transaction::TYPE_PAYMENT])) {
            throw new \Exception('Refund amount only available on charges and payments.');
        }

        $refunded = new Money($this->currency, 0);
        foreach (TransactionTreeIterator::make($this) as $transaction) {
            if (Transaction::TYPE_REFUND == $transaction->type) {
                $refunded = $refunded->add($transaction->transactionAmount());
            }
        }

        return $refunded;
    }

    /**
     * Builds a transaction tree recursively with this
     * transaction as the root node.
     */
    public function tree(): TransactionTree
    {
        if (!isset($this->_tree)) {
            $this->_tree = new TransactionTree($this);
        }

        return $this->_tree;
    }

    //
    // Setters
    //

    public function clearTree(): void
    {
        unset($this->_tree);
    }

    //
    // Validators
    //

    /**
     * Validates the value is not zero.
     */
    public static function notZero(mixed $value): bool
    {
        return 0 != $value;
    }

    //
    // EventObjectInterface
    //

    public function getCreatedEventType(): ?EventType
    {
        // Do not create an event if the transaction
        // belongs to a payment and the company does
        // not have the "transaction_events" feature flag.
        if ($this->payment && !$this->tenant()->features->has('transaction_events')) {
            return null;
        }

        return EventType::TransactionCreated;
    }

    public function getUpdatedEventType(): ?EventType
    {
        // Do not create an event if the transaction
        // belongs to a payment and the company does
        // not have the "transaction_events" feature flag.
        if ($this->payment && !$this->tenant()->features->has('transaction_events')) {
            return null;
        }

        return EventType::TransactionUpdated;
    }

    public function getDeletedEventType(): ?EventType
    {
        // Do not create an event if the transaction
        // belongs to a payment and the company does
        // not have the "transaction_events" feature flag.
        if ($this->payment && !$this->tenant()->features->has('transaction_events')) {
            return null;
        }

        return EventType::TransactionDeleted;
    }

    public function getEventAssociations(): array
    {
        $associations = [
            ['customer', $this->customer],
        ];
        if ($this->invoice) {
            $associations[] = ['invoice', $this->invoice];
        }
        if ($this->credit_note_id) {
            $associations[] = ['credit_note', $this->credit_note_id];
        }
        if ($this->estimate_id) {
            $associations[] = ['estimate', $this->estimate_id];
        }
        if ($this->parent_transaction) {
            $associations[] = ['transaction', $this->parent_transaction];
        }

        return $associations;
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['customer']);
    }

    //
    // PdfDocumentInterface
    //

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        // always defer receipt generation to parent transaction
        if ($parent = $this->parentTransaction()) {
            return $parent->getPdfBuilder();
        }

        return new TransactionPdf($this);
    }

    //
    // ThemeableInterface
    //

    public function getThemeVariables(): PdfVariablesInterface
    {
        return new TransactionPdfVariables($this);
    }

    /**
     * Gets the breakdown for how this payment was applied,
     * including any invoices, refunds, and credits.
     *
     * @throws \Exception when called on a transaction that is not a charge or payment
     *
     * @return array ['invoices' => [], 'creditNotes' => [], 'refunded' => Money, 'credited' => Money]
     */
    public function breakdown(): array
    {
        if (!in_array($this->type, [Transaction::TYPE_CHARGE, Transaction::TYPE_PAYMENT])) {
            throw new \Exception('Breakdown only available on charges and payments.');
        }

        if (isset($this->_cachedBreakdown)) {
            return $this->_cachedBreakdown;
        }

        // add up all invoices, credit notes, refunds, and credits associated with this transaction
        $invoices = [];
        $creditNotes = [];
        $refunded = new Money($this->currency, 0);
        $credited = new Money($this->currency, 0);

        foreach (TransactionTreeIterator::make($this) as $transaction) {
            if (in_array($transaction->type, [Transaction::TYPE_CHARGE, Transaction::TYPE_PAYMENT]) && $invoice = $transaction->invoice()) {
                $invoices[] = $invoice;
            } elseif (Transaction::TYPE_REFUND == $transaction->type) {
                $refunded = $refunded->add($transaction->transactionAmount());
            } elseif (Transaction::TYPE_ADJUSTMENT == $transaction->type) {
                // NOTE subtracting instead of adding because
                // credits are negative.
                $credited = $credited->subtract($transaction->transactionAmount());
            }

            if (in_array($transaction->type, [Transaction::TYPE_CHARGE, Transaction::TYPE_PAYMENT]) && $creditNote = $transaction->creditNote()) {
                $creditNotes[] = $creditNote;
            }
        }

        $this->_cachedBreakdown = [
            'invoices' => $invoices,
            'creditNotes' => $creditNotes,
            'refunded' => $refunded,
            'credited' => $credited,
        ];

        return $this->_cachedBreakdown;
    }

    public function isReconcilable(): bool
    {
        if ($this->skipReconciliation) {
            return false;
        }

        if ($payment = $this->payment) {
            return $payment->isReconcilable();
        }

        if (Transaction::STATUS_SUCCEEDED != $this->status) {
            return false;
        }

        if (Transaction::TYPE_REFUND == $this->type) {
            return true;
        }

        return null === $this->parent_transaction;
    }

    /**
     * Reconcile models to the accounting system.
     * All payments are create only.
     * This inheritance is needed to provide support for the pending -> success transformation
     * all other statuses (except direct success) will be filtered in isReconcilable method.
     */
    public static function writeToAccountingSystem(AbstractEvent $event, string $eventName): void
    {
        if (ModelUpdated::getName() === $eventName) {
            $eventName = ModelCreated::getName();
        }
        parent::writeToAccountingSystem($event, $eventName);
    }

    /**
     * Marks the transaction as convenience fee.
     */
    public function markConvenienceFee(): void
    {
        $this->notes = 'Convenience Fee';
    }

    /**
     * Check if transaction is marked as convenience fee.
     */
    public function isConvenienceFee(): bool
    {
        return 'Convenience Fee' == $this->notes;
    }

    public function getAccountingObjectReference(): InvoicedObjectReference
    {
        $formatter = MoneyFormatter::get();
        $description = $formatter->format($this->paymentAmount());
        if ($reference = $this->gateway_id) {
            $description = "$reference: $description";
        }

        return new InvoicedObjectReference($this->object, (string) $this->id(), $description);
    }
}
