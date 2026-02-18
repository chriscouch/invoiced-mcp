<?php

namespace App\PaymentPlans\Models;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Libs\EventSpoolFacade;
use App\ActivityLog\Traits\EventModelTrait;
use App\ActivityLog\ValueObjects\PendingDeleteEvent;

/**
 * This model represents invoice payment plans.
 *
 * @property int                      $id
 * @property int                      $invoice
 * @property int                      $invoice_id
 * @property string                   $status
 * @property int                      $approval_id
 * @property PaymentPlanInstallment[] $installments
 * @property PaymentPlanApproval|null $approval
 */
class PaymentPlan extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;

    const STATUS_PENDING_SIGNUP = 'pending_signup';
    const STATUS_ACTIVE = 'active';
    const STATUS_FINISHED = 'finished';
    const STATUS_CANCELED = 'canceled';

    const INSTALLMENTS_LIMIT = 100;

    /** @var PaymentPlanInstallment[] */
    private array $_installments;

    //
    // Model Overrides
    //

    protected static function getProperties(): array
    {
        return [
            'invoice_id' => new Property(
                type: Type::INTEGER,
                required: true,
                in_array: false,
                relation: Invoice::class,
            ),
            'status' => new Property(
                validate: ['enum', 'choices' => ['pending_signup', 'active', 'finished', 'canceled']],
                default: self::STATUS_ACTIVE,
            ),
            'approval_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
                relation: PaymentPlanApproval::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'verifyInstallments']);
        self::created([self::class, 'saveInstallments']);

        parent::initialize();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['installments'] = [];
        foreach ($this->installments as $installment) {
            $result['installments'][] = $installment->toArray();
        }
        $approval = $this->approval;
        $result['approval'] = $approval ? $approval->toArray() : null;

        return $result;
    }

    //
    // Hooks
    //

    /**
     * Verifies the attached installments do not exceed the
     * invoice balance.
     */
    public static function verifyInstallments(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $invoice = $model->relation('invoice_id');
        if (!($invoice instanceof Invoice)) {
            throw new ListenerException('No such invoice: '.$model->invoice, ['field' => 'invoice']);
        }

        // can only add payment plans to unpaid invoices
        $currency = $invoice->currency;
        $invoiceBalance = Money::fromDecimal($currency, $invoice->balance);
        if ($invoiceBalance->isZero()) {
            throw new ListenerException('Payment plans cannot be applied to invoices without a balance.', ['field' => 'invoice']);
        }

        // need at least one installment
        $total = new Money($currency, 0);
        $installments = $model->installments;
        if (0 === count($installments)) {
            throw new ListenerException('Missing payment installments.', ['field' => 'installments']);
        }

        // there is a limit to the number of installments a payment plan can have
        if (count($installments) > self::INSTALLMENTS_LIMIT) {
            throw new ListenerException('This payment plan has too many installments. The maximum number of installments is '.self::INSTALLMENTS_LIMIT.' and this plan has '.count($installments).' installments.', ['field' => 'installments']);
        }

        // verify the installment balances add up to the invoice balance
        foreach ($installments as $installment) {
            if (!is_numeric($installment->balance)) {
                $installment->balance = $installment->amount;
            }

            $amount = Money::fromDecimal($currency, $installment->balance);
            $total = $total->add($amount);
        }

        if (!$total->equals($invoiceBalance)) {
            throw new ListenerException('The payment plan installments ('.$total.') must add up to the invoice balance ('.$invoiceBalance.').', ['field' => 'installments']);
        }

        // order the installments by date
        usort($installments, [self::class, 'sortInstallments']);
        $model->installments = $installments;
    }

    /**
     * Saves the attached installments.
     */
    public static function saveInstallments(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        foreach ($model->installments as $installment) {
            $installment->payment_plan_id = (int) $model->id();
            if (!$installment->save()) {
                throw new ListenerException('Could not save payment plan installment: '.$installment->getErrors(), ['field' => 'installments']);
            }
        }
    }

    //
    // Accessors
    //

    /**
     * Gets the payment plan installments.
     *
     * @return PaymentPlanInstallment[]
     */
    protected function getInstallmentsValue(): array
    {
        if (!isset($this->_installments) && $id = $this->id()) {
            $this->_installments = PaymentPlanInstallment::where('payment_plan_id', $id)
                ->sort('date ASC,id ASC')
                ->first(self::INSTALLMENTS_LIMIT);
        }

        return $this->_installments;
    }

    /**
     * Gets the attached payment plan approval.
     */
    protected function getApprovalValue(): ?PaymentPlanApproval
    {
        return $this->relation('approval_id');
    }

    //
    // Mutators
    //

    /**
     * Sets the payment plan installments.
     *
     * @param PaymentPlanInstallment[] $installments
     */
    protected function setInstallmentsValue(array $installments): array
    {
        $this->_installments = $installments;

        return $installments;
    }

    //
    // Relationships
    //

    /**
     * Gets the associated invoice.
     */
    public function invoice(): Invoice
    {
        $invoice = $this->relation('invoice_id');
        if ($invoice) {
            $invoice->setRelation('payment_plan_id', $this);
        }

        return $invoice;
    }

    //
    // Setters
    //

    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice_id = (int) $invoice->id();
        $this->setRelation('invoice_id', $invoice);
    }

    /**
     * Applies a payment amount to this payment plan.
     */
    public function applyPayment(Money $payment): bool
    {
        if ($payment->isZero()) {
            return false;
        }

        // apply payment to installments
        $remaining = $payment;
        $invoice = $this->invoice();
        $currency = $invoice->currency;
        $balances = [];
        $installments = $this->installments;

        // a payment will apply to the oldest installments first
        // whereas a refund will apply to the latest installments first
        if ($payment->isNegative()) {
            $installments = array_reverse($installments);
        }

        foreach ($installments as $installment) {
            $balance = Money::fromDecimal($currency, $installment->balance);

            // ensure the amount applied does not cause the balance
            // to exceed the installment amount
            if ($remaining->isPositive()) {
                // payments: cannot exceed installment balance
                $apply = $remaining->min($balance);
            } else {
                // refunds: cannot exceed amount paid on installment
                $amount = Money::fromDecimal($currency, $installment->amount);
                $amountPaid = $amount->subtract($balance);
                $apply = $remaining->max($amountPaid->negated());
            }

            $balances[] = $balance->subtract($apply);

            $remaining = $remaining->subtract($apply);
            if ($remaining->isZero()) {
                break;
            }
        }

        // cannot perform operation if there is an unapplied
        // payment amount left over
        if (!$remaining->isZero()) {
            return false;
        }

        // save updated installment balances
        $saved = true;
        foreach ($balances as $k => $amount) {
            $installment = $installments[$k];
            $installment->balance = $amount->toDecimal();
            $saved = $installment->save() && $saved;
        }

        if (!$saved) {
            return false;
        }

        // check if the payment plan is finished
        $finished = true;
        foreach ($installments as $installment) {
            if ($installment->balance > 0) {
                $finished = false;

                break;
            }
        }

        // mark the status as finished
        if ($finished) {
            $this->status = self::STATUS_FINISHED;
        } elseif (self::STATUS_FINISHED === $this->status) {
            // remark the payment plan as active
            // if going from paid -> unpaid
            $this->status = self::STATUS_ACTIVE;
        }

        $this->save();

        // When there is a successful payment collected
        // the attempt count needs to be reset on payment
        // plan invoices because each installment starts
        // with a fresh payment retry schedule.
        if (!$finished) {
            $invoice->attempt_count = 0;
        }

        // schedule the next payment attempt by triggering a status update
        $invoice->updateStatus();

        if ('paid' === $invoice->status) {
            $invoice->setUpdatedEventType(EventType::InvoicePaid);
        }

        return true;
    }

    /**
     * Cancels this payment plan.
     */
    public function cancel(): bool
    {
        // cannot cancel a plan that's already been canceled or finished
        if (in_array($this->status, [self::STATUS_FINISHED, self::STATUS_CANCELED])) {
            return false;
        }

        EventSpool::disablePush();

        // set the status on this plan to canceled
        $this->status = self::STATUS_CANCELED;

        if (!$this->save()) {
            EventSpool::enablePop();

            return false;
        }

        // create a payment_plan.deleted event
        $metadata = $this->getEventObject();
        $associations = $this->getEventAssociations();

        EventSpool::enablePop();
        $pendingEvent = new PendingDeleteEvent($this, EventType::PaymentPlanDeleted, $metadata, $associations);
        EventSpoolFacade::get()->enqueue($pendingEvent);

        // detach plan from invoice
        return $this->invoice()->detachPaymentPlan();
    }

    public static function sortInstallments(PaymentPlanInstallment $a, PaymentPlanInstallment $b): int
    {
        return $a->date > $b->date ? 1 : -1;
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        $invoice = $this->invoice();

        $associations = [
            ['customer', $invoice->customer],
        ];
        if ($this->invoice_id) {
            $associations[] = ['invoice', $this->invoice_id];
        }

        return $associations;
    }

    public function getEventObject(): array
    {
        $result = ModelNormalizer::toArray($this);
        $result['customer'] = $this->invoice()->customer()->toArray();

        return $result;
    }

    public function calculateBalance(): array
    {
        $invoice = $this->invoice();
        $currency = $invoice->currency;
        $balance = Money::fromDecimal($currency, 0);
        $pastDueBalance = Money::fromDecimal($currency, 0);
        $age = 0;
        $pastDueAge = 0;
        $processedFutureInstallment = false;
        // The age of the first installment age is based on the invoice date
        // instead of the installment date.
        $previousAge = $invoice->age;

        foreach ($this->installments as $installment) {
            // These 2 checks make sure that we only process AT MOST one
            // future dated installment. This is necessary in order to
            // get an accurate balance owed. The first installment dated
            // in the future is considered due by the customer.
            if ($processedFutureInstallment) {
                break;
            }

            // Signal to the next iteration that only one more installment
            // should be processed.
            if ($installment->date > time()) {
                $processedFutureInstallment = true;
            }

            // Balance
            $installmentBalance = Money::fromDecimal($currency, $installment->balance);
            $balance = $balance->add($installmentBalance);
            if (!$installmentBalance->isPositive()) {
                $previousAge = $installment->age;
                continue;
            }

            // Age
            $age = max($age, $previousAge);
            $previousAge = $installment->age;

            // Past Due Age / Balance
            if ($installment->date < time()) {
                $pastDueBalance = $pastDueBalance->add($installmentBalance);
                $pastDueAge = max($pastDueAge, $installment->age);
            }
        }

        return [
            'balance' => $balance->toDecimal(),
            'age' => $age,
            'pastDueBalance' => $pastDueBalance->toDecimal(),
            'pastDueAge' => $pastDueBalance->isPositive() ? $pastDueAge : null,
        ];
    }

    public function relation(string $name): Model|null
    {
        if ('customer' === $name) {
            return $this->invoice()->customer();
        }

        return parent::relation($name);
    }
}
