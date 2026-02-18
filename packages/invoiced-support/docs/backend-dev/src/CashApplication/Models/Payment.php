<?php

namespace App\CashApplication\Models;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Libs\EventSpoolFacade;
use App\ActivityLog\Traits\EventModelTrait;
use App\ActivityLog\ValueObjects\PendingDeleteEvent;
use App\CashApplication\EmailVariables\PaymentEmailVariables;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Exceptions\ApplyCreditBalancePaymentException;
use App\CashApplication\Pdf\PaymentPdf;
use App\CashApplication\Pdf\PaymentPdfVariables;
use App\CashApplication\Traits\HasAppliedToTrait;
use App\Core\Files\Traits\HasAttachmentsTrait;
use App\Core\I18n\Currencies;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\HasCustomerRestrictionsTrait;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Pdf\PdfDocumentInterface;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Search\Traits\SearchableTrait;
use App\Core\Utils\AppUrl;
use App\Core\Utils\ModelNormalizer;
use App\Core\Utils\ModelUtility;
use App\Core\Utils\Traits\HasClientIdTrait;
use App\Core\Utils\Traits\HasModelLockTrait;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingWritableModel;
use App\Integrations\AccountingSync\ValueObjects\InvoicedObjectReference;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Integrations\Plaid\Models\PlaidItem;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Traits\MetadataTrait;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Traits\SendableDocumentTrait;
use App\Themes\Interfaces\PdfBuilderInterface;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Interfaces\ThemeableInterface;
use App\Themes\Traits\ThemeableTrait;
use Throwable;

/**
 * A payment represents an exchange of money that has been made. Payments can be applied, unapplied, or partially
 * applied which is indicated by a balance that does not equal the amount. Once an payment has been fully applied
 * (ie. the balance reaches 0), it will be marked applied.
 *
 * @property int                      $id
 * @property int|null                 $customer
 * @property float                    $amount
 * @property string                   $currency
 * @property float                    $balance
 * @property float                    $surcharge_percentage
 * @property int                      $date
 * @property string                   $method
 * @property string|null              $reference
 * @property string                   $source
 * @property string|null              $notes
 * @property bool                     $applied
 * @property bool                     $voided
 * @property string|null              $external_id
 * @property string|null              $ach_sender_id
 * @property string|null              $payee
 * @property PlaidItem|null           $plaid_bank_account
 * @property int|null                 $plaid_bank_account_id
 * @property bool|null                $matched
 * @property int|null                 $date_voided
 * @property Charge|null              $charge
 * @property int|null                 $charge_id
 * @property array[]                  $applied_to
 * @property string|null              $pdf_url
 * @property BankFeedTransaction|null $bank_feed_transaction
 */
class Payment extends AccountingWritableModel implements EventObjectInterface, PdfDocumentInterface, SendableDocumentInterface, ThemeableInterface, MetadataModelInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;
    use HasAppliedToTrait;
    use HasAttachmentsTrait;
    use HasClientIdTrait;
    use SearchableTrait;
    use SendableDocumentTrait;
    use ThemeableTrait;
    use HasCustomerRestrictionsTrait;
    use MetadataTrait;
    use HasModelLockTrait;

    // Source Types
    const SOURCE_KEYED = 'keyed';
    const SOURCE_IMPORTED = 'imported';
    const SOURCE_BANK_FEED = 'bank_feed';
    const SOURCE_CHECK_LOCKBOX = 'check_lockbox';
    const SOURCE_REMITTANCE_ADVICE = 'remittance_advice';
    const SOURCE_NETWORK = 'network';

    // These are transaction types which do not affect
    // the cash received balance because they are credit
    // or adjustment activities.
    const NON_CASH_SPLIT_TYPES = [
        PaymentItemType::AppliedCredit->value,
        PaymentItemType::CreditNote->value,
        PaymentItemType::DocumentAdjustment->value,
    ];

    /** @var Transaction[] */
    private array $transactionsToCleanUp = [];

    private array $cachedBreakdown;

    protected static function getProperties(): array
    {
        return [
            'customer' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: Customer::class,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
                default: 0,
            ),
            'currency' => new Property(
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'balance' => new Property(
                type: Type::FLOAT,
                required: true,
                validate: ['callable', 'fn' => [self::class, 'notNegative']],
            ),
            'surcharge_percentage' => new Property(
                type: Type::FLOAT,
                required: false,
                default: 0.00,
                persisted: false,
                in_array: true,
            ),
            'date' => new Property(
                type: Type::DATE_UNIX,
                required: true,
                validate: 'timestamp',
                default: 'now',
            ),
            'method' => new Property(
                required: true,
                default: PaymentMethod::OTHER,
            ),
            'reference' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'source' => new Property(
                type: Type::STRING,
                required: true,
                default: self::SOURCE_KEYED,
            ),
            'notes' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'applied' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
            'voided' => new Property(
                type: Type::BOOLEAN,
            ),
            'external_id' => new Property(
                type: Type::STRING,
                null: true,
                in_array: false,
            ),
            'ach_sender_id' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payee' => new Property(
                type: Type::STRING,
                null: true,
                in_array: false,
            ),
            'plaid_bank_account' => new Property(
                null: true,
                in_array: false,
                belongs_to: PlaidItem::class,
            ),
            'plaid_bank_account_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'matched' => new Property(
                type: Type::BOOLEAN,
                null: true,
            ),
            'date_voided' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                validate: 'timestamp',
                in_array: false,
            ),
            'charge' => new Property(
                null: true,
                has_one: Charge::class,
            ),
            'bank_feed_transaction' => new Property(
                null: true,
                belongs_to: BankFeedTransaction::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([self::class, 'inheritCurrency']);
        self::creating([self::class, 'initializeBalance']);
        self::updating([self::class, 'isEditable']);
        self::saving([self::class, 'validateAmount']);
        self::saving([self::class, 'validateBalance']);
        self::saving([self::class, 'validateCustomer']);
        self::updating([self::class, 'changedCustomer']);
        self::updating([self::class, 'changedDateOrMethod']);
        self::updating([self::class, 'changedCurrency']);
        self::saving([self::class, 'markApplied']);
        self::saving([self::class, 'setAttachments']);
        self::saved([self::class, 'saveModelRelationships']);
        self::deleting([self::class, 'cleaningUpTransactions']);
        self::deleted([self::class, 'cleanedUpTransactions']);
    }

    //
    // Hooks
    //

    /**
     * Sets the currency based on the amount.
     */
    public static function inheritCurrency(AbstractEvent $e): void
    {
        /** @var static $model */
        $model = $e->getModel();
        $company = $model->tenant();
        $customer = $model->customer();

        // Fall back to customer currency then
        // to company currency if none given.
        if (!$model->currency) {
            if ($customer && $customer->currency) {
                $model->currency = $customer->currency;
            } else {
                $model->currency = $company->currency;
            }
        }
    }

    /**
     * Sets the balance based on the amount.
     */
    public static function initializeBalance(AbstractEvent $e): void
    {
        /** @var static $model */
        $model = $e->getModel();

        $model->balance = $model->amount;
    }

    /**
     * Checks if the payment can be edited. Payments that are marked `voided` are not editable.
     */
    public static function isEditable(AbstractEvent $e, string $eventName): void
    {
        /** @var static $model */
        $model = $e->getModel();
        $isUpdate = ModelUpdated::getName() == $eventName;

        if ($model->ignoreUnsaved()->voided) {
            self::setAttachments($e);
            $model->saveAttachments($isUpdate);
            throw new ListenerException('The payment is voided and cannot be edited.', ['field' => 'voided']);
        }
    }

    /**
     * Validates the amount is not zero and not less than the balance.
     */
    public static function validateAmount(AbstractEvent $e): void
    {
        /** @var static $model */
        $model = $e->getModel();
        $amount = Money::fromDecimal($model->currency, $model->amount);
        $previousAmount = Money::fromDecimal($model->currency, $model->ignoreUnsaved()->amount ?? 0);
        $balance = Money::fromDecimal($model->currency, $model->ignoreUnsaved()->balance ?? 0);

        if ($amount->isNegative()) {
            throw new ListenerException('The amount cannot be less than 0.', ['field' => 'amount']);
        }

        if ($amount->equals($previousAmount)) {
            return;
        }

        if ($model->voided) {
            $model->balance = $model->amount;

            return;
        }

        if ($amount->lessThan($previousAmount)) {
            $delta = $previousAmount->subtract($amount);
            $newBalance = $balance->subtract($delta);
            $model->balance = max(0, $newBalance->toDecimal());

            // if we are changing the payment application at the same time as the amount
            // then the new balance does not need to be validated because it will be checked
            // later in HasAppliedToTrait.
            if ($model->dirty('applied_to')) {
                return;
            }

            if ($newBalance->isNegative()) {
                throw new ListenerException('The amount cannot cause the balance to go below 0.', ['field' => 'amount']);
            }
        } else {
            $delta = $amount->subtract($previousAmount);
            $newBalance = $balance->add($delta);
            $model->balance = max(0, $newBalance->toDecimal());
        }
    }

    public static function validateBalance(AbstractEvent $e): void
    {
        /** @var static $model */
        $model = $e->getModel();
        $amount = Money::fromDecimal($model->currency, $model->amount);
        $balance = Money::fromDecimal($model->currency, $model->balance);

        if ($balance->greaterThan($amount)) {
            throw new ListenerException('The balance ('.$balance.') cannot be greater than the amount ('.$amount.')', ['field' => 'balance']);
        }
    }

    /**
     * Validates that the customer is valid.
     */
    public static function validateCustomer(AbstractEvent $e): void
    {
        /** @var static $model */
        $model = $e->getModel();
        if ($customerId = $model->customer) {
            $customer = $model->customer();
            if (!$customer) {
                throw new ListenerException('No such customer: '.$customerId);
            }
        }
    }

    public static function changedCustomer(AbstractEvent $e): void
    {
        /** @var static $model */
        $model = $e->getModel();

        $currentCustomer = $model->ignoreUnsaved()->customer;
        if ($currentCustomer && $model->customer != $currentCustomer) {
            try {
                $model->deleteTransactions();

                // Look for new cash application matches if the
                // customer has been removed.
                if (!$model->customer) {
                    $model->matched = null;
                }
            } catch (ModelException $e) {
                throw new ListenerException($e->getMessage());
            }
        }
    }

    public static function changedDateOrMethod(AbstractEvent $e): void
    {
        /** @var static $model */
        $model = $e->getModel();
        $dateChanged = $model->date != $model->ignoreUnsaved()->date;
        $oldMethod = $model->ignoreUnsaved()->method;
        $methodChanged = $model->method != $oldMethod;

        if ($dateChanged || $methodChanged) {
            foreach ($model->getTransactions() as $transaction) {
                if ($dateChanged) {
                    $transaction->date = $model->date;
                }

                if ($methodChanged && $transaction->method == $oldMethod) {
                    $transaction->method = $model->method;
                }

                $transaction->save();
            }
        }
    }

    public static function changedCurrency(AbstractEvent $e): void
    {
        /** @var static $model */
        $model = $e->getModel();
        if ($model->dirty('currency', true) && count($model->getTransactions()) > 0) {
            throw new ListenerException('The currency cannot be modified if the payment is applied. You must first unapply the payment to change the currency.');
        }
    }

    /**
     * Marks the payment as applied once the balance reaches 0.
     */
    public static function markApplied(AbstractEvent $e): void
    {
        /** @var static $model */
        $model = $e->getModel();
        $balance = Money::fromDecimal($model->currency, $model->balance);
        $model->applied = $balance->isZero();
    }

    public static function setAttachments(AbstractEvent $e): void
    {
        /** @var static $model */
        $model = $e->getModel();

        // save any file attachments
        if (isset($model->attachments) && is_array($model->attachments)) { /* @phpstan-ignore-line */
            $model->_saveAttachments = $model->attachments;
            unset($model->attachments);
        }
    }

    /**
     * Saves the relationships on the payment.
     */
    public static function saveModelRelationships(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $isUpdate = $event instanceof ModelUpdated;

        try {
            $model->saveAppliedTo($isUpdate);
        } catch (ApplyCreditBalancePaymentException $e) {
            throw new ListenerException('An error occurred while trying to apply payment: '.$e->getMessage(), ['field' => 'applied_to', 'reason' => 'credit_balance']);
        } catch (ListenerException $e) {
            throw new ListenerException('An error occurred while trying to apply payment: '.$e->getMessage(), ['field' => 'applied_to']);
        }

        // BUG refresh() has to be present here because for some reason
        // the model properties like $this->tenant_id are missing at this stage
        if (!$isUpdate) {
            $model->refresh();
        }

        $model->saveAttachments($isUpdate);
    }

    /**
     * Stores transaction to delete in dedicated variable.
     */
    public static function cleaningUpTransactions(AbstractEvent $event): void
    {
        /** @var static $model */
        $model = $event->getModel();
        $model->setTransactionsToCleanUp($model->getTransactions());
    }

    /**
     * Cleans up transactions after model is deleted.
     */
    public static function cleanedUpTransactions(AbstractEvent $event): void
    {
        /** @var static $model */
        $model = $event->getModel();
        foreach ($model->getTransactionsToCleanUp() as $transaction) {
            // this prevents transaction deleted events (payment deleted event is already set)
            $transaction->payment = $model;
            if (!$transaction->delete()) {
                throw new ModelException('Could not delete transaction: '.$transaction->getErrors());
            }
        }
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['pdf_url'] = $this->pdf_url;
        $charge = $this->charge;
        $result['charge'] = $charge ? $charge->toArray() : null;
        $result['metadata'] = $this->metadata;

        $this->toArrayHook($result, [], [], []);

        return $result;
    }

    public function toArrayHook(array &$result, array $exclude, array $include, array $expand): void
    {
        if ($this->_noArrayHook) {
            $this->_noArrayHook = false;

            return;
        }

        // customer name
        if (isset($include['customerName'])) {
            if ($this->customer()) {
                $result['customerName'] = $this->customer()->name;
            } else {
                $result['customerName'] = null;
            }
        }
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
     * Generates the PDF receipt URL for this payment.
     */
    protected function getPdfUrlValue(): ?string
    {
        if (!$this->applied || $this->voided || !$this->customer) {
            return null;
        }

        return AppUrl::get()->build().'/payments/'.$this->tenant()->identifier.'/'.$this->client_id.'/pdf';
    }

    protected function getBankAccountNameValue(): ?string
    {
        if ($bankAccount = $this->plaid_bank_account) {
            return $bankAccount->account_name.' *'.$bankAccount->account_last4;
        }

        return null;
    }

    public function getFlywirePaymentValue(): ?FlywirePayment
    {
        return FlywirePayment::where('ar_payment_id', $this)->oneOrNull();
    }

    //
    // Validators
    //

    /**
     * Validates the value is not negative.
     */
    public static function notNegative(mixed $value): bool
    {
        return 0 <= $value;
    }

    //
    // Relationships
    //

    /**
     * Sets the associated customer.
     */
    public function setCustomer(Customer $customer): void
    {
        $this->customer = (int) $customer->id();
        $this->setRelation('customer', $customer);
    }

    /**
     * Gets the associated customer.
     */
    public function customer(): ?Customer
    {
        return $this->relation('customer');
    }

    //
    // Getters
    //

    /**
     * Gets a list of transactions associated with this payment.
     *
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        $query = Transaction::queryWithTenant($this->tenant())
            ->where('payment_id', $this);

        return ModelUtility::getAllModels($query);
    }

    public function getFlywirePayments(): array
    {
        $query = FlywirePayment::queryWithTenant($this->tenant())
            ->where('ar_payment_id', $this->id);

        return ModelUtility::getAllModels($query);
    }

    public function getAmount(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount);
    }

    /**
     * Gets the money formatting options for this object.
     */
    public function moneyFormat(): array
    {
        return $this->customer()?->moneyFormat() ?? $this->tenant()->moneyFormat();
    }

    public function getMethod(): PaymentMethod
    {
        return new PaymentMethod(['tenant_id' => $this->tenant_id, 'id' => $this->method]);
    }

    /**
     * Gets the breakdown for how this payment was applied,
     * including any invoices, refunds, and credits.
     */
    public function breakdown(): array
    {
        if (isset($this->cachedBreakdown)) {
            return $this->cachedBreakdown;
        }

        // iterate over all transactions and add up
        // any invoices/credit notes/estimates/credits in the process
        $invoices = [];
        $creditNotes = [];
        $estimates = [];
        $credited = new Money($this->currency, 0);
        $convenienceFee = new Money($this->currency, 0);
        $surchargeFee = new Money($this->currency, 0);

        foreach ($this->getTransactions() as $transaction) {
            if ($creditNote = $transaction->creditNote()) {
                $creditNotes[] = $creditNote;
            }
            if ($estimate = $transaction->estimate()) {
                $estimates[] = $estimate;
            }
            if ($invoice = $transaction->invoice()) {
                $invoices[] = $invoice;
            }

            if (Transaction::TYPE_ADJUSTMENT == $transaction->type && PaymentMethod::BALANCE != $transaction->method) {
                // NOTE subtracting instead of adding because credits are negative.
                $credited = $credited->subtract($transaction->transactionAmount());
            }

            if ($transaction->isConvenienceFee()) {
                $convenienceFee = $convenienceFee->add($transaction->transactionAmount());
            }
        }

        foreach ($this->getFlywirePayments() as $flywirePayment) {
            if ($flywirePayment->surcharge_percentage && $flywirePayment->surcharge_percentage > 0) {
                $amount = (int) round($flywirePayment->surcharge_percentage * $flywirePayment->amount_to, 0); // surcharge percentage example: 0.03
                $surchargeFee = $surchargeFee->add(new Money($this->currency, $amount));
            }
        }

        $this->cachedBreakdown = [
            'convenienceFee' => $convenienceFee,
            'surchargeFee' => $surchargeFee,
            'creditNotes' => $creditNotes,
            'credited' => $credited,
            'estimates' => $estimates,
            'invoices' => $invoices,
        ];

        return $this->cachedBreakdown;
    }

    public function isReconcilable(): bool
    {
        return parent::isReconcilable() && AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM !== $this->source;
    }

    //
    // Setters
    //

    /**
     * Voids the payment. This operation is irreversible.
     *
     * @throws ModelException
     */
    public function void(): void
    {
        if ($this->voided) {
            throw new ModelException('This payment has already been voided.');
        }

        $db = self::getDriver()->getConnection(null);

        $ownsDatabaseTransaction = false;
        if (!$db->isTransactionActive()) {
            $db->beginTransaction();
            $ownsDatabaseTransaction = true;
        }

        try {
            // delete transactions that were used to apply payment
            $this->deleteTransactions();

            // remove suggested matches
            $db->delete('InvoiceUnappliedPaymentAssociations', [
                '`payment_id`' => $this->id(),
            ]);

            // void the payment
            EventSpool::disablePush();
            $this->voided = true;
            $this->date_voided = time();
            try {
                $this->saveOrFail();
            } catch (ModelException $e) {
                EventSpool::enablePop();
                throw $e;
            }
        } catch (Throwable $e) {
            if ($ownsDatabaseTransaction) {
                $db->rollBack();
            }

            throw $e;
        }

        if ($ownsDatabaseTransaction) {
            $db->commit();
        }

        // create a payment.deleted event
        $metadata = $this->getEventObject();
        $associations = $this->getEventAssociations();

        EventSpool::enablePop();
        $pendingEvent = new PendingDeleteEvent($this, EventType::PaymentDeleted, $metadata, $associations);
        EventSpoolFacade::get()->enqueue($pendingEvent);
    }

    /**
     * @throws ModelException
     */
    private function deleteTransactions(): void
    {
        foreach ($this->getTransactions() as $transaction) {
            if (!$transaction->delete()) {
                throw new ModelException('Could not delete transaction: '.$transaction->getErrors());
            }
        }

        $this->balance = $this->amount;
        $this->applied = false;
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        $associations = [];
        if ($customerId = $this->customer) {
            $associations['customer/'.$customerId] = ['customer', $customerId];
        }

        // Add each referenced document
        foreach ($this->applied_to as $split) {
            $invoice = $split['invoice'] ?? null;
            $creditNote = $split['credit_note'] ?? null;
            $estimate = $split['estimate'] ?? null;

            if ($invoice instanceof Invoice) {
                $associations['invoice/'.$invoice->id] = ['invoice', $invoice->id];
            } elseif (is_numeric($invoice) && $invoice > 0) {
                $associations['invoice/'.$invoice] = ['invoice', $invoice];
            }

            if ($creditNote instanceof CreditNote) {
                $associations['credit_note/'.$creditNote->id] = ['credit_note', $creditNote->id];
            } elseif (is_numeric($creditNote) && $creditNote > 0) {
                $associations['credit_note/'.$creditNote] = ['credit_note', $creditNote];
            }

            if ($estimate instanceof Estimate) {
                $associations['estimate/'.$estimate->id] = ['estimate', $estimate->id];
            } elseif (is_numeric($estimate) && $estimate > 0) {
                $associations['estimate/'.$estimate] = ['estimate', $estimate];
            }
        }

        return array_values($associations);
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, include: ['applied_to'], expand: ['customer']);
    }

    //
    // SendableDocumentInterface
    //

    public function getSendCustomer(): Customer
    {
        return $this->customer(); /* @phpstan-ignore-line */
    }

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new PaymentEmailVariables($this);
    }

    public function schemaOrgActions(): ?string
    {
        return null; // not used for payment receipts
    }

    public function getSendClientUrl(): ?string
    {
        return null;
    }

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        return new PaymentPdf($this);
    }

    public function getThreadName(): string
    {
        return 'Payment Receipt for '.MoneyFormatter::get()->format($this->getAmount());
    }

    //
    // ThemeableInterface
    //

    public function getThemeVariables(): PdfVariablesInterface
    {
        return new PaymentPdfVariables($this);
    }

    protected function writeToLegacyMetadataStorage(): bool
    {
        return false;
    }

    protected function writeToAttributeStorage(): bool
    {
        return true;
    }

    protected function readFromAttributeStorage(): bool
    {
        return true;
    }

    public function getMetadataTablePrefix(): string
    {
        return 'Payment';
    }

    //
    // Getters/Setters
    //

    /**
     * @return Transaction[]
     */
    public function getTransactionsToCleanUp(): array
    {
        return $this->transactionsToCleanUp;
    }

    /**
     * @param Transaction[] $transactionsToCleanUp
     */
    public function setTransactionsToCleanUp(array $transactionsToCleanUp): void
    {
        $this->transactionsToCleanUp = $transactionsToCleanUp;
    }

    public function getAccountingObjectReference(): InvoicedObjectReference
    {
        $formatter = MoneyFormatter::get();
        $description = $formatter->format($this->getAmount());
        if ($reference = $this->reference) {
            $description = "$reference: $description";
        }

        return new InvoicedObjectReference($this->object, (string) $this->id(), $description);
    }

    public function getSurchargePercentage(): float
    {
        return $this->surcharge_percentage;
    }

    public function setSurchargePercentage(float $surchargePercentage): void
    {
        $this->surcharge_percentage = $surchargePercentage;
    }
}
