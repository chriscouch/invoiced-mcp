<?php

namespace App\AccountsReceivable\Models;

use App\AccountsReceivable\EmailVariables\InvoiceEmailVariables;
use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Interfaces\HasShipToInterface;
use App\AccountsReceivable\Interfaces\ModelAgeInterface;
use App\AccountsReceivable\Libs\InvoiceStatusGenerator;
use App\AccountsReceivable\Libs\PaymentTermsFactory;
use App\AccountsReceivable\Pdf\InvoicePdf;
use App\AccountsReceivable\Pdf\InvoicePdfVariables;
use App\AccountsReceivable\Traits\HasShipToTrait;
use App\AccountsReceivable\ValueObjects\CalculatedInvoice;
use App\ActivityLog\Enums\EventType;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\CreditBalance;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Chasing\CustomerChasing\ChasingInvoiceListener;
use App\Chasing\Legacy\InvoiceChasingScheduler;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Chasing\Models\PromiseToPay;
use App\Core\I18n\TranslatorFacade;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Core\Orm\Validator;
use App\Core\Statsd\StatsdFacade;
use App\Core\Utils\AppUrl;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Traits\AccountingWritableModelTrait;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Interfaces\HasPaymentSourceInterface;
use App\PaymentProcessing\Libs\PaymentScheduler;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountRouting;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Traits\HasPaymentSourceTrait;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\SalesTax\ValueObjects\SalesTaxInvoiceItem;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Libs\EmailSpoolFacade;
use App\Sending\Email\Libs\EmailTriggers;
use App\SubscriptionBilling\Models\CouponRedemption;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Subscription;
use App\Themes\Interfaces\PdfBuilderInterface;
use App\Themes\Interfaces\PdfVariablesInterface;
use Exception;

/**
 * This model represents an invoice. Probably the most important class ever.
 *
 * @property int|null          $due_date
 * @property int|null          $date_paid
 * @property int|null          $date_bad_debt
 * @property bool              $late_fees
 * @property bool              $chase
 * @property int|null          $subscription_id
 * @property Subscription|null $subscription
 * @property string|null       $payment_terms
 * @property string            $collection_mode
 * @property bool              $autopay
 * @property int|null          $payment_plan_id
 * @property int|null          $payment_plan
 * @property float             $amount_paid
 * @property float             $amount_credited
 * @property float             $amount_written_off
 * @property bool              $paid
 * @property float             $balance
 * @property int|null          $last_sent
 * @property int|null          $next_chase_on
 * @property string|null       $next_chase_step
 * @property bool              $needs_attention
 * @property bool              $recalculate_chase
 * @property int               $attempt_count
 * @property int|null          $next_payment_attempt
 * @property bool              $consolidated
 * @property int|null          $consolidated_invoice_id
 * @property string|null       $payment_url
 * @property array|null        $expected_payment_date
 * @property array             $tags
 */
class Invoice extends ReceivableDocument implements ModelAgeInterface, AccountingWritableModelInterface, HasShipToInterface, HasPaymentSourceInterface
{
    use AccountingWritableModelTrait;
    use HasShipToTrait;
    use HasPaymentSourceTrait;

    const MAX_TAGS = 10;
    const TAG_LENGTH = 50;

    private static array $chaseProperties = [
        'draft',
        'chase',
        'paid',
        'closed',
        'due_date',
        'last_sent',
        'autopay',
    ];

    private static int $minimumEmailTimespan = 5; // minutes

    private static array $disabledPaymentMethods = [
        'credit_card' => false,
        'ach' => false,
        'paypal' => false,
        'check' => false,
        'wire_transfer' => false,
        'cash' => false,
        'other' => false,
    ];

    private bool $_markedPaid = false;
    private Money $_amountPaidDelta;
    private array $_emailVariables = [];
    private ?array $_savePromiseToPay = null;
    private ?array $_tags = null;
    private ?array $_saveTags = null;
    private array $_pendingCredits = [];
    private PaymentScheduler $_paymentScheduler;
    private ?array $_disabledPaymentMethods = null;
    private bool $_skipSubscriptionUpdate = false;
    /** @var PendingLineItem[] */
    private array $_pendingLineItems;

    /**
     * flag to identify if the status was converted from
     * pending to failed by cron job.
     */
    private bool $_fromPendingToFailed = false;
    /**
     * Do not send invoice notifications.
     */
    private bool $muted = false;

    protected static function getProperties(): array
    {
        return [
            'due_date' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'date_paid' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
            'date_bad_debt' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
            'chase' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'subscription' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                belongs_to: Subscription::class,
            ),
            'payment_terms' => new Property(
                null: true,
                validate: ['string', 'min' => 1, 'max' => 255],
            ),
            'autopay' => new Property(
                type: Type::BOOLEAN,
            ),
            // TODO deprecated
            'collection_mode' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['auto', 'manual']],
                in_array: false,
            ),
            'next_chase_on' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'next_chase_step' => new Property(
                null: true,
                validate: ['enum', 'choices' => ['email', 'flag', 'sms']],
                in_array: false,
            ),
            'needs_attention' => new Property(
                type: Type::BOOLEAN,
            ),

            /* Computed Properties */

            'amount_paid' => new Property(
                type: Type::FLOAT,
                in_array: false,
            ),
            'amount_credited' => new Property(
                type: Type::FLOAT,
                in_array: false,
            ),
            'amount_written_off' => new Property(
                type: Type::FLOAT,
                in_array: false,
            ),
            'paid' => new Property(
                type: Type::BOOLEAN,
            ),
            'balance' => new Property(
                type: Type::FLOAT,
            ),
            'last_sent' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
            'recalculate_chase' => new Property(
                type: Type::BOOLEAN,
                default: false,
                in_array: false,
            ),
            'attempt_count' => new Property(
                type: Type::INTEGER,
            ),
            'payment_plan_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
                relation: PaymentPlan::class,
            ),
            'payment_plan' => new Property(
                relation: PaymentPlan::class,
                local_key: 'payment_plan_id',
            ),

            /* AutoPay Invoices */

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
            'next_payment_attempt' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),

            /* Consolidated Invoices */

            'consolidated' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
            'consolidated_invoice_id' => new Property(
                null: true,
                in_array: false,
                relation: Invoice::class,
            ),

            /* Late Fee */
            'late_fees' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([self::class, 'verifySubscription'], 2);
        self::creating([self::class, 'calculateInvoice']);
        self::creating([self::class, 'checkCreditLimits']);
        self::created([self::class, 'setupInvoiceChasing']);

        self::updating([self::class, 'beforeUpdateInvoice'], -201);
        self::updating([self::class, 'updateChasingSchedule'], -202);

        self::created([self::class, 'generateCreditNote']);
        self::saved([self::class, 'statsd']);
        self::saved([self::class, 'paidEvent']);
        self::saved([self::class, 'autoApplyCredits']);
        self::updated([self::class, 'updatePaymentPlan']);
        self::saved([self::class, 'updateSubscriptionStatus']);
        self::saved([self::class, 'approvePaymentPlansWithoutAutoPay']);

        ChasingInvoiceListener::listen();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['url'] = $this->url;
        $result['pdf_url'] = $this->pdf_url;
        $result['csv_url'] = $this->csv_url;
        $result['payment_url'] = $this->payment_url;
        $shipTo = $this->ship_to;
        $result['ship_to'] = $shipTo ? $shipTo->toArray() : null;
        $paymentSource = $this->payment_source;
        $result['payment_source'] = $paymentSource ? $paymentSource->toArray() : null;
        $result['metadata'] = $this->metadata;

        return $result;
    }

    protected function getMassAssignmentBlocked(): ?array
    {
        return ['subtotal', 'total', 'status', 'viewed', 'client_id', 'client_id_exp', 'date_paid', 'date_bad_debt', 'subscription_id', 'amount_paid', 'amount_credited', 'paid', 'balance', 'last_sent', 'attempt_count', 'recalculate_chase', 'payment_plan_id', 'created_at', 'updated_at'];
    }

    //
    // Hooks
    //

    /**
     * Verifies the subscription relationship when creating.
     */
    public static function verifySubscription(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        $sid = $invoice->subscription_id;
        if (!$sid) {
            return;
        }

        if (!$invoice->subscription) {
            throw new ListenerException("No such subscription: $sid", ['field' => 'subscription']);
        }
    }

    /**
     * Calculates an invoice before saving.
     */
    public static function calculateInvoice(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        // calculate the balance
        $total = Money::fromDecimal($invoice->currency, $invoice->total);
        $amountPaid = Money::fromDecimal($invoice->currency, $invoice->amount_paid ?? 0)
            ->max(new Money($invoice->currency, 0));
        $amountCredited = Money::fromDecimal($invoice->currency, $invoice->amount_credited ?? 0)
            ->max(new Money($invoice->currency, 0));

        $invoice->amount_paid = $amountPaid->toDecimal();
        $invoice->amount_credited = $amountCredited->toDecimal();
        $invoice->balance = $total->subtract($amountPaid)
            ->subtract($amountCredited)
            ->toDecimal();

        // voided invoices have the balance zeroed
        if ($invoice->voided) {
            $invoice->balance = 0;
        }

        // set paid flag
        $invoice->paid = $invoice->balance <= 0 && !$invoice->draft && !$invoice->closed && !$invoice->voided;
        $invoice->_markedPaid = $invoice->paid;
        if ($invoice->_markedPaid) {
            $lastPayment = $invoice->getLatestPayment();
            $invoice->date_paid = $lastPayment ? $lastPayment->date : time();
        }

        // close paid, non-draft invoices
        if ($invoice->paid && !$invoice->draft && !$invoice->closed) {
            $invoice->closed = true;
        }

        // inherit chasing from account settings
        $company = $invoice->tenant();
        if (!$invoice->dirty('chase')) {
            $invoice->chase = $company->accounts_receivable_settings->chase_new_invoices;
        }

        // determine payment terms
        $customer = new Customer(['id' => $invoice->customer]);
        if (!$invoice->due_date && !$invoice->dirty('payment_terms')) {
            // inherit payment_terms from customer
            if ($customer->payment_terms) {
                $invoice->payment_terms = $customer->payment_terms;
                // inherit payment terms from settings
            } else {
                $invoice->payment_terms = $company->accounts_receivable_settings->payment_terms;
            }
        }

        // inherit AutoPay from customer if missing
        if (!$invoice->dirty('autopay')) {
            $invoice->autopay = $customer->autopay;
        }

        // handle AutoPay invoices
        if ($invoice->autopay) {
            // validate the company supports AutoPay
            if (!PaymentMethod::acceptsAutoPay($company)) {
                throw new ListenerException('autopay_not_supported', ['field' => 'autopay']);
            }

            // AutoPay invoices have special payment terms
            $invoice->payment_terms = TranslatorFacade::get()->trans('payment_terms.autopay', [], 'general', $customer->getLocale());
        }

        // calculate due date from payment terms
        if (!$invoice->due_date) {
            $terms = PaymentTermsFactory::get((string) $invoice->payment_terms);
            $date = 'now' == $invoice->date ? time() : $invoice->date;
            /** @var Property $property */
            $property = Invoice::definition()->get('date');
            if (!Validator::validateProperty($invoice, $property, $date)) {
                throw new ListenerException('Invalid date', ['field' => 'date']);
            }
            $invoice->due_date = $terms->getDueDate($date);
        }

        // determine the invoice's status
        $status = InvoiceStatusGenerator::get($invoice);
        $invoice->status = $status->value;

        // LEGACY FEATURE: legacy_chasing
        // schedule next chase date
        $chaser = new InvoiceChasingScheduler();
        [$nextChase, $action] = $chaser->calculateNextChase($invoice);
        $invoice->next_chase_on = $nextChase;
        $invoice->next_chase_step = $action;
        $invoice->recalculate_chase = false;

        // schedule the next payment attempt
        $scheduler = $invoice->getPaymentScheduler();
        $invoice->next_payment_attempt = $scheduler->next();

        // save expected payment date
        if ($invoice->expected_payment_date) {
            $invoice->_savePromiseToPay = $invoice->expected_payment_date;
            unset($invoice->expected_payment_date);
        }

        // save invoice tags
        if ($invoice->dirty('tags')) {
            $invoice->_saveTags = $invoice->tags;
            unset($invoice->tags);
        }
    }

    public static function checkCreditLimits(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();
        $customer = $invoice->customer();
        if (!($customer instanceof Customer)) {
            return;
        }

        if ($customer->credit_hold) {
            throw new ListenerException('New invoices cannot be created for this account because it has a credit hold.', ['field' => 'total']);
        }

        if ($customer->credit_limit > 0) {
            $creditLimit = Money::fromDecimal($invoice->currency, $customer->credit_limit);

            $outstanding = self::getDriver()->getConnection(null)->createQueryBuilder()
                ->select('sum(balance)')
                ->from('Invoices')
                ->andWhere('tenant_id = '.$invoice->tenant_id)
                ->andWhere('customer = '.$customer->id())
                ->andWhere('closed = 0')
                ->andWhere('draft = 0')
                ->andWhere('voided = 0')
                ->andWhere('currency = :currency')
                ->setParameter('currency', $invoice->currency)
                ->fetchOne();

            $outstanding = Money::fromDecimal($invoice->currency, $outstanding ?? 0);
            $invoiceTotal = Money::fromDecimal($invoice->currency, $invoice->total);
            $potentialOutstanding = $outstanding->add($invoiceTotal);

            if ($potentialOutstanding->greaterThan($creditLimit)) {
                throw new ListenerException("This invoice cannot be created because the new amount outstanding ($potentialOutstanding) exceeds the account's credit limit ($creditLimit).", ['field' => 'total']);
            }
        }
    }

    /**
     * Generates a credit note for any pending charges.
     */
    public static function generateCreditNote(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        if (0 === count($invoice->_pendingCredits)) {
            return;
        }

        $wasIssued = $invoice->_wasIssued;

        $creditNote = new CreditNote();
        $creditNote->setCustomer($invoice->customer());
        $creditNote->date = $invoice->date;
        $creditNote->currency = $invoice->currency;
        $creditNote->draft = $invoice->draft;

        $items = [];
        foreach ($invoice->_pendingCredits as $line) {
            // make the line positive because it's going to be
            // added to a credit note
            $line['quantity'] = abs($line['quantity']);
            $line['unit_cost'] = abs($line['unit_cost']);
            $line['amount'] = abs($line['amount']);
            $items[] = $line;
        }
        $creditNote->items = $items;
        if (!$creditNote->save()) {
            throw new ListenerException('Could not save credit note', ['field' => 'credit_note']);
        }

        $invoice->_pendingCredits = [];

        // Only apply the credit note if it is not a draft
        if (!$creditNote->draft) {
            $applyToInvoice = Money::fromDecimal($creditNote->currency, min($invoice->balance, $creditNote->total));
            $appliedTo = [
                [
                    'type' => PaymentItemType::CreditNote->value,
                    'credit_note' => $creditNote,
                    'document_type' => 'invoice',
                    'invoice' => $invoice,
                    'amount' => $applyToInvoice->toDecimal(),
                ],
            ];

            // issue a credit from the credit note, if there is a balance
            // later we might want to make this behavior a setting
            $creditNoteTotal = Money::fromDecimal($creditNote->currency, $creditNote->total);
            if ($creditNoteTotal->greaterThan($applyToInvoice)) {
                $appliedTo[] = [
                    'type' => PaymentItemType::CreditNote->value,
                    'credit_note' => $creditNote,
                    'amount' => $creditNoteTotal->subtract($applyToInvoice)->toDecimal(),
                ];
            }

            $payment = new Payment();
            $payment->setCustomer($invoice->customer());
            $payment->date = $invoice->date;
            $payment->currency = $invoice->currency;
            $payment->applied_to = $appliedTo;

            if (!$payment->save()) {
                throw new ListenerException('Could not apply generated credit note: '.$payment->getErrors(), ['field' => 'credit_note']);
            }
        }

        // reset this value for later event listeners
        // since the model has been edited
        // after generating the credit note
        $invoice->_wasIssued = $wasIssued;
    }

    public static function statsd(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();
        if ($invoice->_wasIssued) {
            StatsdFacade::get()->increment('invoice.issued');
        }
    }

    /**
     * Sends thank you emails and fires an invoice.paid event when an
     * invoice is paid in full.
     */
    public static function paidEvent(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        if ($invoice->isMuted()) {
            return;
        }

        $wasPaid = $invoice->_markedPaid && !$invoice->draft && $invoice->total > 0;
        if (!$wasPaid) {
            $invoice->setUpdatedEventType(EventType::InvoiceUpdated);

            return;
        }

        // this renames the event emitted from the generic `invoice.updated` to `invoice.paid`
        $invoice->setUpdatedEventType(EventType::InvoicePaid);

        // send a thank you
        if (EmailTriggers::make($invoice->tenant())->isEnabled('invoice_paid')) {
            $emailTemplate = (new DocumentEmailTemplateFactory())->get($invoice);
            EmailSpoolFacade::get()->spoolDocument($invoice, $emailTemplate, [], false);
        }

        // mark related promise to pay as kept
        /** @var PromiseToPay[] $promises */
        $promises = PromiseToPay::where('invoice_id', $invoice)
            ->where('kept', false)
            ->where('date', time(), '>=')
            ->all();
        foreach ($promises as $promise) {
            $promise->kept = true;
            $promise->save();
        }
    }

    public static function beforeUpdateInvoice(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        // calculate the balance
        $total = Money::fromDecimal($invoice->currency, $invoice->total);
        $amountPaid = Money::fromDecimal($invoice->currency, $invoice->amount_paid ?? 0)
            ->max(new Money($invoice->currency, 0));
        $amountCredited = Money::fromDecimal($invoice->currency, $invoice->amount_credited ?? 0)
            ->max(new Money($invoice->currency, 0));

        $invoice->amount_paid = $amountPaid->toDecimal();
        $invoice->amount_credited = $amountCredited->toDecimal();
        $invoice->balance = $total->subtract($amountPaid)
            ->subtract($amountCredited)
            ->toDecimal();

        // voided invoices have the balance zeroed
        if ($invoice->voided) {
            $invoice->balance = 0;
        }

        // calculate amount paid delta
        $delta = $invoice->amount_paid - $invoice->ignoreUnsaved()->amount_paid;
        $delta += $invoice->amount_credited - $invoice->ignoreUnsaved()->amount_credited;
        $invoice->_amountPaidDelta = Money::fromDecimal($invoice->currency, $delta);

        // set paid flag
        $invoice->paid = $invoice->balance <= 0 && !$invoice->draft && !$invoice->voided && InvoiceStatus::BadDebt->value !== $invoice->status;

        // close paid invoices
        if ($invoice->paid && !$invoice->dirty('closed')) {
            $invoice->closed = true;
        }

        // check if the invoice is going from unpaid -> paid
        $invoice->_markedPaid = $invoice->paid && !$invoice->ignoreUnsaved()->paid;
        if ($invoice->_markedPaid) {
            $lastPayment = $invoice->getLatestPayment();
            $invoice->date_paid = $lastPayment ? $lastPayment->date : time();
        }

        // check if the invoice is going from paid -> unpaid
        if (!$invoice->paid && $invoice->ignoreUnsaved()->paid) {
            $invoice->date_paid = null;
        }

        // AutoPay invoices have special payment terms
        if ($invoice->autopay && $invoice->dirty('autopay', true) && !$invoice->payment_plan_id) {
            $invoice->payment_terms = TranslatorFacade::get()->trans('payment_terms.autopay', [], 'general', $invoice->customer()->getLocale());
        }

        // re-calculate due date when changing the date or payment terms
        $date = $invoice->date;
        $paymentTerms = $invoice->payment_terms;
        if (!$invoice->dirty('due_date') && $date && $paymentTerms && ($date != $invoice->ignoreUnsaved()->date || $paymentTerms != $invoice->ignoreUnsaved()->payment_terms) && !$invoice->payment_plan_id) {
            $terms = PaymentTermsFactory::get($paymentTerms);
            $invoice->due_date = $terms->getDueDate($date);
        }

        // check if being marked as sent
        if ($invoice->dirty('sent') && $invoice->sent) {
            $invoice->last_sent = time();
        }

        // determine the invoice's status
        $status = InvoiceStatusGenerator::get($invoice);
        $invoice->status = $status->value;

        // should not be allowed to edit the total if the invoice is pending
        if (InvoiceStatus::Pending->value == $invoice->status && $invoice->total != $invoice->ignoreUnsaved()->total) {
            throw new ListenerException('The invoice total cannot be modified when there is a pending payment. Please wait for the payment to clear before modifying the invoice.', ['field' => 'total']);
        }

        // schedule next chase date when certain properties change
        foreach (self::$chaseProperties as $prop) {
            if ($invoice->$prop != $invoice->ignoreUnsaved()->$prop) {
                $chaser = new InvoiceChasingScheduler();
                [$nextChase, $action] = $chaser->calculateNextChase($invoice);
                $invoice->next_chase_step = $action;
                $invoice->next_chase_on = $nextChase;

                break;
            }
        }

        // do not allow chasing to be recalculated if it has just been set
        if ($invoice->dirty('next_chase_on')) {
            $invoice->recalculate_chase = false;
        }

        if ($invoice->isFromPendingToFailed()) {
            // we override next payment attempt set when we move
            // invoice to pending state
            $invoice->next_payment_attempt = time();
            $invoice->next_payment_attempt = $invoice->getPaymentScheduler()->nextFailedAttempt();
        }

        // schedule the next payment attempt (unless provided)
        if (!$invoice->dirty('next_payment_attempt')) {
            $scheduler = $invoice->getPaymentScheduler();
            $invoice->next_payment_attempt = $scheduler->next();
        }

        // remove payment plan if it's been canceled
        $paymentPlan = $invoice->paymentPlan();
        if ($paymentPlan) {
            if (PaymentPlan::STATUS_CANCELED == $paymentPlan->status) {
                $invoice->payment_plan_id = null;
            } else {
                // the total cannot be changed if the invoice has a payment plan attached
                if ($invoice->total != $invoice->ignoreUnsaved()->total) {
                    throw new ListenerException('The invoice total cannot be modified when there is an active payment plan attached. Please remove the payment plan before modifying the invoice.', ['field' => 'total']);
                }
            }
        }

        // save expected payment date
        if ($invoice->dirty('expected_payment_date')) {
            $invoice->_savePromiseToPay = $invoice->expected_payment_date;
            unset($invoice->expected_payment_date);
        }

        // save invoice tags
        if ($invoice->dirty('tags')) {
            $invoice->_saveTags = $invoice->tags;
            unset($invoice->tags);
        }

        // bugfix: prevent balance concurrency issues
        // by only writing balance when changing
        $keys = ['total', 'amount_paid', 'amount_credited', 'balance', 'paid'];
        foreach ($keys as $k) {
            if (!$invoice->dirty($k, true)) {
                unset($invoice->$k);
            }
        }
    }

    /**
     * Saves the relationships on the invoice.
     */
    public static function saveModelRelationships(AbstractEvent $event, string $eventName): void
    {
        parent::saveModelRelationships($event, $eventName);

        /** @var Invoice $invoice */
        $invoice = $event->getModel();
        $isUpdate = ModelUpdated::getName() == $eventName;

        $invoice->saveTags($isUpdate);
        $invoice->savePromiseToPay($isUpdate);
    }

    /**
     * Applies any credits to the invoice.
     */
    public static function autoApplyCredits(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        if ($invoice->_wasIssued && !$invoice->paid && $invoice->tenant()->accounts_receivable_settings->auto_apply_credits) {
            try {
                $invoice->applyCredits();
            } catch (ModelException $e) {
                throw new ListenerException($e->getMessage(), ['field' => 'balance']);
            }
        }
    }

    /**
     * Approves payment plans automatically when AutoPay is disabled.
     */
    public static function approvePaymentPlansWithoutAutoPay(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();
        if ($invoice->autopay) {
            return;
        }

        $paymentPlan = $invoice->paymentPlan();
        if (!$paymentPlan) {
            return;
        }

        if (PaymentPlan::STATUS_PENDING_SIGNUP == $paymentPlan->status) {
            $paymentPlan->status = PaymentPlan::STATUS_ACTIVE;
            $paymentPlan->save();
        }
    }

    /**
     * Updates the attached subscription's status.
     */
    public static function updateSubscriptionStatus(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        if ($invoice->_skipSubscriptionUpdate) {
            return;
        }

        // if this is a subscription invoice, trigger an
        // update on the subscription status
        if ($subscription = $invoice->subscription) {
            try {
                $subscription->updateStatus($invoice);
            } catch (ModelException $e) {
                throw new ListenerException($e->getMessage(), ['field' => 'subscription']);
            }
        }
    }

    /**
     * Updates the balance of an attached payment plan after a payment
     * is applied to the invoice.
     */
    public static function updatePaymentPlan(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        // only apply payments when the balance decrements.
        // we do not want to update payment plan installments
        // when there is a refund or anything else to cause the balance
        // to increase.
        if (!$invoice->_amountPaidDelta->isZero() && $paymentPlan = $invoice->paymentPlan()) {
            $paymentPlan->applyPayment($invoice->_amountPaidDelta);
        }
    }

    /**
     * Adds a chasing schedule to the invoice based on the default InvoiceChasingCadence.
     *
     * @throws ListenerException
     */
    public static function setupInvoiceChasing(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();
        if (!$invoice->tenant()->features->has('smart_chasing') ||
            !$invoice->tenant()->features->has('invoice_chasing') ||
            $invoice->paid ||
            $invoice->closed ||
            $invoice->voided ||
            $invoice->date_bad_debt) {
            return;
        }

        $defaultCadence = InvoiceChasingCadence::where('default', true)
            ->oneOrNull();
        if (!($defaultCadence instanceof InvoiceChasingCadence)) {
            return;
        }

        $delivery = new InvoiceDelivery();
        $delivery->invoice = $invoice;
        $delivery->applyCadence($defaultCadence);
        if (!$delivery->save()) {
            throw new ListenerException('Failed to configure chasing: '.((string) $delivery->getErrors()));
        }
    }

    /**
     * Updates the InvoiceDelivery to be reprocessed when necessary
     * based on invoice value changes.
     */
    public static function updateChasingSchedule(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();
        // Set of properties that should trigger
        // the InvoiceDelivery to be reprocessed
        // if they have changed value.
        $triggers = [
            'date',
            'draft',
            'due_date',
            'paid',
            'closed',
            'voided',
            'date_bad_debt',
        ];

        $reprocessDelivery = false;
        foreach ($triggers as $trigger) {
            if ($invoice->$trigger != $invoice->ignoreUnsaved()->$trigger) {
                $reprocessDelivery = true;
                break;
            }
        }

        if (!$reprocessDelivery) {
            return;
        }

        // Reprocess InvoiceDelivery
        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id())
            ->where("chase_schedule != '[]'")
            ->oneOrNull();
        if (!($delivery instanceof InvoiceDelivery)) {
            return;
        }

        // disable chasing if the invoice is closed
        if (
            $invoice->paid ||
            $invoice->closed ||
            $invoice->voided
        ) {
            $delivery->disabled = true;
        }
        $delivery->processed = false;
        if (!$delivery->save()) {
            throw new ListenerException('Failed to refresh chasing schedule: '.((string) $delivery->getErrors()));
        }
    }

    //
    // Mutators
    //

    /**
     * Sets the `autopay` value.
     */
    protected function setAutopayValue(mixed $enabled): mixed
    {
        if ($enabled) {
            $this->collection_mode = AccountsReceivableSettings::COLLECTION_MODE_AUTO;
        } else {
            $this->collection_mode = AccountsReceivableSettings::COLLECTION_MODE_MANUAL;
        }

        return $enabled;
    }

    /**
     * Validates inputted tag values.
     *
     * @return array formatted tags
     */
    protected function setTagsValue(mixed $tags): array
    {
        // validate tags
        if (!is_array($tags)) {
            $tags = [];
        }

        $tags = array_unique(array_slice($tags, 0, self::MAX_TAGS));
        $filtered = [];
        foreach ($tags as $tag) {
            // Allowed characters: a-z, A-Z, 0-9, _, -
            // Min length: 1
            if (!preg_match('/^[a-z0-9_-]{1,}$/i', $tag)) {
                continue;
            }

            // max of 50 chars.
            $filtered[] = substr($tag, 0, self::TAG_LENGTH);
        }

        $this->_tags = $filtered;

        return $filtered;
    }

    //
    // ReceivableDocument
    //

    public static function getDefaultDocumentTitle(): string
    {
        return 'Invoice';
    }

    protected function getUrlValue(): ?string
    {
        if (!$this->client_id || $this->voided) {
            return null;
        }

        return AppUrl::get()->build().'/invoices/'.$this->tenant()->identifier.'/'.$this->client_id;
    }

    protected function getPdfUrlValue(): ?string
    {
        $url = $this->getUrlValue();

        return $url ? "$url/pdf" : null;
    }

    /**
     * Generates the URL to download this invoice as a CSV.
     */
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
            'preview' => $preview,
        ]);
    }

    //
    // Accessors
    //

    /**
     * Gets the `collection_mode` property.
     */
    protected function getCollectionModeValue(): string
    {
        if ($this->autopay) {
            return AccountsReceivableSettings::COLLECTION_MODE_AUTO;
        }

        return AccountsReceivableSettings::COLLECTION_MODE_MANUAL;
    }

    /**
     * Generates the URL for the payment screen.
     * Paid, closed and AutoPay invoices do not have
     * a payment URL.
     */
    protected function getPaymentUrlValue(): ?string
    {
        // cannot pay on any invoice if:
        // i) closed
        // ii) already paid
        // iii) pending
        // iv) voided
        // v) no client id
        if ($this->closed || $this->paid || InvoiceStatus::Pending->value == $this->status || $this->voided || !$this->client_id) {
            return null;
        }

        // cannot pay AutoPay invoices if:
        // i) customer has an attached payment source, AND
        // ii) payment has not been attempt yet OR
        //     there is a scheduled payment attempt
        if ($this->autopay && $this->customer) {
            $customer = $this->customer();
            if ($customer->payment_source && (0 == $this->attempt_count || $this->next_payment_attempt) && !$this->paymentPlan()) {
                return null;
            }
        }

        // cannot pay payment plan invoices if:
        // i) AutoPay is enabled, AND
        // ii) the payment plan is pending onboarding
        if ($this->autopay && $paymentPlan = $this->paymentPlan()) {
            if (PaymentPlan::STATUS_PENDING_SIGNUP == $paymentPlan->status) {
                return null;
            }
        }

        return AppUrl::get()->build().'/invoices/'.$this->tenant()->identifier.'/'.$this->client_id.'/payment';
    }

    /**
     * Gets the disabled payment methods.
     */
    protected function getDisabledPaymentMethodsValue(): array
    {
        if (null === $this->_disabledPaymentMethods) {
            $methods = [];

            if ($id = $this->id) {
                $models = DisabledPaymentMethod::where('object_type', ObjectType::Invoice->typeName())
                    ->where('object_id', $id)
                    ->all();

                foreach ($models as $model) {
                    $methods[$model->method] = true;
                }
            }

            $this->_disabledPaymentMethods = $methods;
        }

        return array_replace(
            self::$disabledPaymentMethods,
            $this->_disabledPaymentMethods
        );
    }

    /**
     * @deprecated
     *
     * Gets any invoice tags
     */
    protected function getTagsValue(mixed $tags): array
    {
        if (!$this->hasId()) {
            return (array) $tags;
        }

        if (!is_array($this->_tags)) {
            $this->_tags = (array) self::getDriver()->getConnection(null)->fetchOne('SELECT tag FROM InvoiceTags WHERE invoice_id = ?', [$this->id()]);
        }

        return $this->_tags;
    }

    /**
     * Gets the payment_plan value. An alias for payment_plan_id.
     */
    protected function getPaymentPlanValue(): ?int
    {
        return $this->payment_plan_id;
    }

    /**
     * Gets the expected_payment_date property.
     *
     * @param false|array|null $value
     */
    protected function getExpectedPaymentDateValue($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (false === $value) {
            return null;
        }

        $date = PromiseToPay::where('invoice_id', $this)
            ->sort('id DESC')
            ->oneOrNull();

        if (!$date) {
            return null;
        }

        return $date->toArray();
    }

    public function getAgeValue(): int
    {
        return (int) floor((time() - $this->date) / 86400);
    }

    public function getPastDueAgeValue(): ?int
    {
        if (InvoiceStatus::PastDue->value != $this->status) {
            return null;
        }

        return (int) floor((time() - $this->due_date) / 86400);
    }

    //
    // Getters
    //

    /**
     * Gets the attached payment plan.
     */
    public function paymentPlan(): ?PaymentPlan
    {
        $paymentPlan = $this->relation('payment_plan_id');
        if ($paymentPlan) {
            $paymentPlan->setRelation('invoice_id', $this);
        }

        return $paymentPlan;
    }

    //
    // Utility Functions
    //

    /**
     * Includes any pending line items when creating this
     * invoice.
     *
     * @param bool $applySubDiscountsTaxes when true, applies discounts and taxes at line item level for subscription line items
     *
     * @throws Exception when the invoice has no pending line items
     *
     * @return $this
     */
    public function withPending(bool $applySubDiscountsTaxes = false)
    {
        if ($this->hasId()) {
            throw new Exception('Can only call withPending() for new invoices.');
        }

        // segment the charges and credits from the pending lines
        // charges will be added to this invoice and credits
        // will be added to a credit note
        $pendingLines = $this->getPendingLineItems($applySubDiscountsTaxes);
        $pendingCharges = [];
        $pendingCredits = [];
        foreach ($pendingLines as $line) {
            if ($line['amount'] < 0) {
                $pendingCredits[] = $line;
            } else {
                $pendingCharges[] = $line;
            }
        }

        $this->items = array_merge(
            (array) $this->items,
            $pendingCharges
        );
        $this->_pendingCredits = $pendingCredits;

        if (0 === count($this->items) && 0 === count($pendingCredits)) {
            throw new Exception('Unable to create an invoice because the customer does not have any pending line items.');
        }

        return $this;
    }

    /**
     * Applies any available credits to this invoice.
     *
     * @throws ModelException if the credit cannot be saved
     */
    public function applyCredits(): void
    {
        if (!$this->customer) {
            return;
        }

        // apply available credits from customer credit balance
        $customer = $this->customer();
        $availableCredits = max(0, CreditBalance::lookup($customer, $this->currency)->toDecimal());

        $creditCharge = min($this->balance, $availableCredits);
        if ($creditCharge > 0) {
            $payment = new Payment();
            $payment->setCustomer($customer);
            $payment->currency = $this->currency;
            $payment->applied_to = [
                [
                    'type' => PaymentItemType::AppliedCredit->value,
                    'document_type' => 'invoice',
                    'invoice' => $this,
                    'amount' => $creditCharge,
                ],
            ];
            if ($payment->save()) {
                $this->refresh();
            } else {
                if (!$payment->getErrors()->has('credit_balance', 'reason')) {
                    throw new ListenerException('Error while saving payment '.$payment->getErrors());
                }
            }
        }

        // apply open credit notes
        $creditNotes = CreditNote::where('customer', $customer)
            ->where('paid', false)
            ->where('draft', false)
            ->where('closed', false)
            ->where('voided', false)
            ->where('date', time(), '<=')
            ->where('currency', $this->currency)
            ->first(100);
        foreach ($creditNotes as $creditNote) {
            $creditCharge = min($this->balance, $creditNote->balance);
            if ($creditCharge <= 0) {
                continue;
            }

            $payment = new Payment();
            $payment->setCustomer($customer);
            $payment->currency = $this->currency;
            $payment->applied_to = [
                [
                    'type' => PaymentItemType::CreditNote->value,
                    'credit_note' => $creditNote,
                    'document_type' => 'invoice',
                    'invoice' => $this,
                    'amount' => $creditCharge,
                ],
            ];
            $payment->saveOrFail();

            $this->refresh();
        }
    }

    /**
     * Attaches a payment plan to this invoice.
     */
    public function attachPaymentPlan(PaymentPlan $paymentPlan, bool $autopay, bool $requireApproval): bool
    {
        $paymentPlan->setInvoice($this);
        foreach ($this->discounts as $discount) {
            if ($discount->isExpirable()) {
                $this->getErrors()->add("Payment plan can't be applied to an invoice with discount with an expiration date.");

                return false;
            }
        }

        $paymentPlan->status = PaymentPlan::STATUS_ACTIVE;
        // require approval for AutoPay payment plans if requested
        if ($autopay && $requireApproval) {
            $paymentPlan->status = PaymentPlan::STATUS_PENDING_SIGNUP;
        }

        // save any changes to the payment plan model
        if (!$paymentPlan->save()) {
            foreach ($paymentPlan->getErrors()->all() as $error) {
                $this->getErrors()->add($error);
            }

            return false;
        }

        $this->payment_plan_id = (int) $paymentPlan->id();

        // update the payment terms
        $this->autopay = $autopay;
        $this->payment_terms = TranslatorFacade::get()->trans('payment_terms.payment_plan', [], 'general', $this->customer()->getLocale());

        // the invoice due date should be set to a day after the last installment is due
        $installments = $paymentPlan->installments;
        /** @var PaymentPlanInstallment $lastInstallment */
        $lastInstallment = end($installments);
        $this->due_date = $lastInstallment->date + 86400;

        // reset the payment attempt count to 0
        // otherwise the next payment date will not be scheduled
        $this->attempt_count = 0;

        return $this->save();
    }

    /**
     * Detaches a payment plan.
     */
    public function detachPaymentPlan(): bool
    {
        // disable AutoPay and remove the payment terms / due date
        $this->payment_terms = '';
        $this->due_date = null;
        $this->autopay = false;

        // NOTE we don't actually clear the payment plan ID
        // here. The updated hook will handle that. This
        // will allow the payment scheduler to recognize
        // that the plan was canceled and unschedule any
        // payment attempts before detaching the plan.

        return $this->save();
    }

    /**
     * Gets the latest payment for this invoice.
     */
    public function getLatestPayment(): ?Transaction
    {
        return Transaction::where('invoice', $this->id())
            ->where('type IN ("'.Transaction::TYPE_CHARGE.'","'.Transaction::TYPE_PAYMENT.'")')
            ->where('status', Transaction::STATUS_SUCCEEDED)
            ->sort('date DESC')
            ->oneOrNull();
    }

    /**
     * Applies a payment.
     *
     * @throws ModelException if the payment cannot be saved
     */
    public function applyPayment(Money $amount): void
    {
        $this->amount_paid += $amount->toDecimal();

        // Applying a payment to the invoice means that
        // it can no longer be a draft.
        if ($this->draft) {
            $this->draft = false;
        }

        // When the payment amount is negative, this could potentially
        // cause the invoice to go from paid -> unpaid. If this
        // happens then the invoice would be marked as bad debt since
        // the invoice would remain closed. Instead we should explicitly
        // reopen the invoice so the user has the choice to mark the
        // invoice as bad debt or collect the remaining balance.
        if ($amount->isNegative()) {
            $this->closed = false;
        }

        $this->skipClosedCheck()
            ->saveOrFail();
    }

    /**
     * Applies a credit from a credit note.
     *
     * @throws ModelException if the credit cannot be saved
     */
    public function applyCredit(Money $amount): void
    {
        $this->amount_credited += $amount->toDecimal();

        // Applying a credit to the invoice means that
        // it can no longer be a draft.
        if ($this->draft) {
            $this->draft = false;
        }

        // When the credit amount is negative, this could potentially
        // cause the invoice to go from paid -> unpaid. If this
        // happens then the invoice would be marked as bad debt since
        // the invoice would remain closed. Instead we should explicitly
        // reopen the invoice so the user has the choice to mark the
        // invoice as bad debt or collect the remaining balance.
        if ($amount->isNegative()) {
            $this->closed = false;
        }

        $this->skipClosedCheck()
            ->saveOrFail();
    }

    /**
     * Gets any pending credits generated for this invoice.
     * NOTE: must call withPending() first.
     */
    public function getPendingCredits(): array
    {
        return $this->_pendingCredits;
    }

    /**
     * Skips the updateStatus() call on the subscription.
     *
     * @return $this
     */
    public function skipSubscriptionUpdate(bool $skip = true)
    {
        $this->_skipSubscriptionUpdate = $skip;

        return $this;
    }

    /**
     * Sets the pending line items for this customer, instead
     * of doing a database lookup.
     *
     * @param PendingLineItem[] $pendingLineItems
     */
    public function setPendingLineItems(array $pendingLineItems): void
    {
        $this->_pendingLineItems = $pendingLineItems;
    }

    /**
     * Gets any pending line items to be added to this invoice.
     *
     * @param bool $applySubDiscountsTaxes when true, applies discounts and taxes at line item level for subscription line items
     */
    protected function getPendingLineItems(bool $applySubDiscountsTaxes): array
    {
        // look up pending line items for customer
        if (!isset($this->_pendingLineItems)) {
            $pendingLineItems = PendingLineItem::where('customer_id', $this->customer);
            if ($subscriptionId = $this->subscription_id) {
                // exclude pending line items associated w/ any subscription that is not
                // the invoice's subscription
                $pendingLineItems = $pendingLineItems->where("(subscription_id IS NULL OR subscription_id = $subscriptionId)");
            }

            $this->_pendingLineItems = $pendingLineItems->first(1000);
        }

        // convert lines to arrays
        $lines = [];
        foreach ($this->_pendingLineItems as $item) {
            $line = $item->toArray();
            $line['pending'] = true;

            // apply discounts/taxes if allowed and this is a subscription line item
            if ($applySubDiscountsTaxes && $subscription = $item->subscription()) {
                $line['discounts'] = array_map(fn (CouponRedemption $redemption) => $redemption->coupon, $subscription->couponRedemptions());

                if ($taxes = $subscription->taxes) {
                    $line['taxes'] = $taxes;
                }
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Saves the invoice tags.
     */
    private function saveTags(bool $isUpdate = false): void
    {
        if (!is_array($this->_saveTags)) {
            return;
        }

        // delete any previously saved tags
        if ($isUpdate) {
            $this->clearTags();
        }

        $tagsToInsert = [];
        foreach ((array) $this->_saveTags as $tag) {
            $tagsToInsert[] = [
                'invoice_id' => $this->id(),
                'tag' => $tag,
            ];
        }

        $database = self::getDriver()->getConnection(null);
        foreach ($tagsToInsert as $row) {
            $database->insert('InvoiceTags', $row);
        }

        $this->_tags = $this->_saveTags;
        $this->_saveTags = null;
    }

    /**
     * Clears any saved tags on this invoice.
     */
    private function clearTags(): void
    {
        self::getDriver()->getConnection(null)->delete('InvoiceTags', ['invoice_id' => $this->id()]);
    }

    /**
     * Saves the expected date.
     */
    private function savePromiseToPay(bool $isUpdate): void
    {
        if (!is_array($this->_savePromiseToPay)) {
            return;
        }

        $promiseToPay = null;
        if ($isUpdate) {
            $promiseToPay = PromiseToPay::where('invoice_id', $this)
                ->sort('id DESC')
                ->oneOrNull();
        }

        if (!$promiseToPay) {
            $promiseToPay = new PromiseToPay();
            $promiseToPay->invoice = $this;
            $promiseToPay->customer = $this->customer();
        }

        $promiseToPay->currency = $this->currency;
        $promiseToPay->amount = $this->balance;
        foreach ($this->_savePromiseToPay as $k => $v) { /* @phpstan-ignore-line */
            $promiseToPay->$k = $v;
        }

        $this->_savePromiseToPay = null;

        $promiseToPay->saveOrFail();
    }

    /**
     * Gets a payment scheduler instance.
     */
    public function getPaymentScheduler(): PaymentScheduler
    {
        if (!isset($this->_paymentScheduler)) {
            $this->_paymentScheduler = new PaymentScheduler($this);
        }

        return $this->_paymentScheduler;
    }

    protected function checkIfVoidable(): void
    {
        if ($this->amount_credited > 0) {
            throw new ModelException('This invoice cannot be voided because it has a credit note applied.');
        }

        if ($this->amount_paid > 0) {
            throw new ModelException('This invoice cannot be voided because it has a payment applied.');
        }

        if (InvoiceStatus::Pending->value == $this->status) {
            throw new ModelException('This invoice cannot be voided because it has a pending payment.');
        }
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        $associations = [
            ['customer', $this->customer],
        ];
        if ($this->subscription_id) {
            $associations[] = ['subscription', $this->subscription_id];
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
        return new InvoicePdfVariables($this);
    }

    //
    // SendableDocumentInterface
    //

    /**
     * When used the next email template that will be sent
     * is the automatic failed payment.
     *
     * @param array $variables extra variables to add to email template
     *
     * @return $this
     */
    public function setEmailVariables(array $variables = [])
    {
        $this->_emailVariables = $variables;

        return $this;
    }

    /**
     * Gets the default email contacts when
     * sending this object.
     */
    public function getDefaultEmailContacts(): array
    {
        // Send to the invoice distribution list when that feature is enabled
        if ($this->tenant()->features->has('invoice_distributions')) {
            $distribution = InvoiceDistribution::where('invoice_id', $this->id())->oneOrNull();
            if ($distribution && $department = $distribution->department) {
                $contacts = Contact::where('customer_id', $this->customer)
                    ->where('department', $department)
                    ->sort('name ASC')
                    ->all();

                $result = [];
                foreach ($contacts as $contact) {
                    $info = [
                        'name' => $contact->name,
                        'email' => trim(strtolower((string) $contact->email)),
                    ];

                    if (filter_var($info['email'], FILTER_VALIDATE_EMAIL)) {
                        $result[] = $info;
                    }
                }

                return $result;
            }
        }

        return $this->getSendCustomer()->emailContacts();
    }

    /**
     * Gets the extra email variables.
     */
    public function getExtraEmailVariables(): array
    {
        return $this->_emailVariables;
    }

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new InvoiceEmailVariables($this);
    }

    public function schemaOrgActions(): ?string
    {
        $buttonText = 'View Invoice';
        if ($this->paid) {
            $description = 'Please review your invoice';
        } else {
            $description = 'Please review and pay your invoice';
        }

        return EmailHtml::schemaOrgViewAction($buttonText, $this->url, $description);
    }

    public function getSendClientUrl(): ?string
    {
        return $this->url;
    }

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        return new InvoicePdf($this);
    }

    /**
     * Handles a failed collection attempt.
     * int $increment - 1 if we need to increment attempt count
     * for example manual transaction | 0 - if state passes from pending
     * in that case attempt is already incremented.
     *
     * @return Invoice - this
     */
    public function handleAutoPayFailure(): Invoice
    {
        // Only update the attempt information and schedule
        // another one if this attempt happened after the
        // currently scheduled attempt. The result should be
        // that failure to collect on an invoice when it's
        // due to be collected should increment the
        // attempt count and schedule a new attempt. Any attempts
        // that happen before the next scheduled attempt (say a
        // user-initiated attempt) should not increment the
        // attempt count and should not increment the next
        // scheduled attempt. The exception is the first payment
        // attempt should always increment the attempt count.
        if (($this->next_payment_attempt <= time() || 0 == $this->attempt_count) && $this->autopay) {
            // increment the number of payment attempts
            ++$this->attempt_count;
            // notify the payment scheduler that this is a failed attempt
            $this->getPaymentScheduler()->failed();

            $this->skipClosedCheck()
                ->save();
        }

        return $this;
    }

    /**
     * Gets the payment source for an invoice.
     */
    public function getPaymentSource(): ?PaymentSource
    {
        if ($source = $this->payment_source) {
            return $source;
        }

        return $this->customer()->payment_source;
    }

    /**
     * Marks the invoice as converted from pending
     * to failed state.
     *
     * @return $this
     */
    public function setFromPendingToFailed(): self
    {
        $this->_fromPendingToFailed = true;

        return $this;
    }

    /**
     * Is the invoice converted from pending
     * to failed state.
     */
    public function isFromPendingToFailed(): bool
    {
        return $this->_fromPendingToFailed;
    }

    /**
     * @return int sequence number of the next try
     */
    public function getNextAttemptNumber(): int
    {
        return $this->attempt_count;
    }

    /**
     * next attempt chould not be higher then
     * current time.
     *
     * @return int last payment attempt or now
     */
    public function getLastAttempt(): int
    {
        return $this->next_payment_attempt ?: time();
    }

    public function getMerchantAccount(PaymentMethod $method): ?MerchantAccount
    {
        return MerchantAccountRouting::findMerchantAccount($method->id, (int) $this->id());
    }

    //
    // AccountingIntegrationModelInterface
    //

    public function isReconcilable(): bool
    {
        return !$this->draft && !$this->skipReconciliation;
    }

    /**
     * Get amount of money to be processed.
     */
    public function amountToProcess(): float
    {
        return $this->balance ?? 0;
    }

    protected function getPermissionName(): string
    {
        return 'invoices';
    }

    public function mute(): void
    {
        $this->muted = true;
    }

    public function unmute(): void
    {
        $this->muted = false;
    }

    public function isMuted(): bool
    {
        return $this->muted;
    }

    public function deleteInvoiceDelivery(): void
    {
        /** @var ?InvoiceDelivery $delivery */
        $delivery = InvoiceDelivery::where('invoice_id', $this->id)->oneOrNull();
        if (!$delivery) {
            return;
        }

        $delivery->disabled = true;
        $delivery->saveOrFail();
    }

    public function createInvoiceDelivery(?array $deliveryData): void
    {
        if (!$deliveryData) {
            return;
        }

        if ($this->closed) {
            return;
        }

        $delivery = null;
        if ($this->id) {
            $delivery = InvoiceDelivery::where('invoice_id', $this->id)->oneOrNull();
        }

        if (!$delivery) {
            $delivery = new InvoiceDelivery();
            $delivery->invoice = $this;
        }

        $delivery->emails = $deliveryData['emails'] ?? null;
        // override chase schedule only if it is set explicitly
        if (isset($deliveryData['chase_schedule'])) {
            $delivery->chase_schedule = $deliveryData['chase_schedule'];
        }

        /** @var InvoiceChasingCadence|null $template */
        $template = null;
        // override cadence_id only if it is set explicitly
        if (isset($deliveryData['cadence_id'])) {
            $delivery->cadence_id = $deliveryData['cadence_id'];
            $template = InvoiceChasingCadence::find($delivery->cadence_id);
            // or set default cadence id on creation only
        } elseif (!$delivery->id) {
            $template = InvoiceChasingCadence::where('default', true)->oneOrNull();
            if ($template) {
                $delivery->cadence_id = $template->id;
            }
        }

        if ($delivery->cadence_id && !$delivery->chase_schedule) {
            if ($template) {
                $delivery->chase_schedule = $template->chase_schedule;
            }
        }

        $delivery->saveOrFail();
    }

    public function getAmountPaid(): float
    {
        return $this->amount_paid;
    }
}
