<?php

namespace App\SubscriptionBilling\Models;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Exception\InvoiceCalculationException;
use App\AccountsReceivable\Interfaces\HasShipToInterface;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Traits\HasCustomerTrait;
use App\AccountsReceivable\Traits\HasShipToTrait;
use App\CashApplication\Models\Transaction;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Multitenant\Models\HasCustomerRestrictionsTrait;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Pdf\PdfDocumentInterface;
use App\Core\Search\Traits\SearchableTrait;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\Core\Utils\Traits\HasClientIdTrait;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventModelTrait;
use App\Core\Utils\Traits\HasModelLockTrait;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Traits\MetadataTrait;
use App\Notifications\Enums\NotificationEventType;
use App\PaymentProcessing\Interfaces\HasPaymentSourceInterface;
use App\PaymentProcessing\Traits\HasPaymentSourceTrait;
use App\SalesTax\Exception\TaxCalculationException;
use App\SalesTax\Models\TaxRate;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Traits\SendableDocumentTrait;
use App\SubscriptionBilling\BillingMode\BillInAdvance;
use App\SubscriptionBilling\BillingMode\BillInArrears;
use App\SubscriptionBilling\EmailVariables\SubscriptionEmailVariables;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Interfaces\BillingModeInterface;
use App\SubscriptionBilling\Libs\BillingPeriods;
use App\SubscriptionBilling\Libs\CancelSubscriptionFacade;
use App\SubscriptionBilling\Libs\ContractPeriods;
use App\SubscriptionBilling\Libs\PricingEngine;
use App\SubscriptionBilling\Metrics\MrrCalculator;
use App\SubscriptionBilling\ValueObjects\BillingPeriod;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use App\Themes\Interfaces\PdfBuilderInterface;
use RuntimeException;

/**
 * A subscription describes a recurring billing scenario
 * in which the recurring amount and interval are fixed.
 *
 * @property int                       $id
 * @property float|null                $amount
 * @property string                    $plan
 * @property int                       $plan_id
 * @property int                       $start_date
 * @property float                     $quantity
 * @property string|null               $description
 * @property int|null                  $cycles
 * @property array                     $addons
 * @property array                     $discounts
 * @property array                     $taxes
 * @property bool                      $cancel_at_period_end
 * @property string                    $contract_renewal_mode
 * @property int|null                  $contract_renewal_cycles
 * @property int|null                  $contract_period_start
 * @property int|null                  $contract_period_end
 * @property int|null                  $period_start
 * @property int|null                  $period_end
 * @property string                    $bill_in
 * @property int                       $bill_in_advance_days
 * @property int|null                  $snap_to_nth_day
 * @property bool                      $pending_renewal
 * @property bool                      $paused
 * @property bool                      $canceled
 * @property bool                      $finished
 * @property int|null                  $approval_id
 * @property int|null                  $renewed_last
 * @property int|null                  $renews_next
 * @property int|null                  $canceled_at
 * @property string|null               $canceled_reason
 * @property string                    $status
 * @property int                       $num_invoices
 * @property float                     $mrr
 * @property float                     $recurring_total
 * @property bool                      $prorate
 * @property string                    $url
 * @property SubscriptionApproval|null $approval
 */
class Subscription extends MultitenantModel implements EventObjectInterface, MetadataModelInterface, PdfDocumentInterface, SendableDocumentInterface, HasShipToInterface, HasPaymentSourceInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;
    use HasClientIdTrait;
    use HasPaymentSourceTrait;
    use HasCustomerRestrictionsTrait;
    use HasShipToTrait;
    use MetadataTrait;
    use SearchableTrait;
    use SendableDocumentTrait;
    use HasCustomerTrait;
    use HasModelLockTrait;

    const RENEWAL_MODE_NONE = 'none';
    const RENEWAL_MODE_MANUAL = 'manual';
    const RENEWAL_MODE_RENEW_ONCE = 'renew_once';
    const RENEWAL_MODE_AUTO = 'auto';

    const BILL_IN_ADVANCE = 'advance';
    const BILL_IN_ARREARS = 'arrears';

    const ADDON_LIMIT = 100;

    private ?Plan $_plan = null;
    private ?array $_saveAddons = null;
    private ?array $_addons = null;
    private ?array $_saveCoupons = null;
    /**
     * @var ?CouponRedemption[]
     */
    private ?array $_couponRedemptions = null;
    /**
     * Preserves taxes during tax calculation.
     */
    public bool $preserveTaxes = false;

    protected static function getProperties(): array
    {
        return [
            'amount' => new Property(
                type: Type::FLOAT,
                null: true,
                validate: ['callable', 'fn' => [self::class, 'validateAmount']],
            ),
            'plan' => new Property(
                required: true,
                relation: Plan::class,
                local_key: 'plan_id',
            ),
            'plan_id' => new Property(
                type: Type::INTEGER,
                required: true,
                in_array: false,
                relation: Plan::class,
            ),
            'start_date' => new Property(
                type: Type::DATE_UNIX,
                required: true,
            ),
            'quantity' => new Property(
                type: Type::FLOAT,
                validate: ['callable', 'fn' => [self::class, 'validateQuantity']],
                default: 1,
            ),
            'description' => new Property(
                null: true,
            ),
            'cycles' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'taxes' => new Property(
                type: Type::ARRAY,
                validate: ['callable', 'fn' => [self::class, 'validateTaxes']],
                default: [],
            ),
            'cancel_at_period_end' => new Property(
                type: Type::BOOLEAN,
            ),
            'canceled_reason' => new Property(
                null: true,
                validate: ['string', 'max' => 50],
            ),
            'paused' => new Property(
                type: Type::BOOLEAN,
            ),
            'bill_in' => new Property(
                validate: ['enum', 'choices' => ['advance', 'arrears']],
                default: self::BILL_IN_ADVANCE,
            ),
            'period_start' => new Property(
                type: Type::DATE_UNIX,
                default: null,
            ),
            'period_end' => new Property(
                type: Type::DATE_UNIX,
                default: null,
            ),
            'bill_in_advance_days' => new Property(
                type: Type::INTEGER,
                default: 0,
            ),

            /* Contracts */

            'contract_renewal_mode' => new Property(
                validate: ['enum', 'choices' => ['none', 'renew_once', 'manual', 'auto']],
                default: self::RENEWAL_MODE_NONE,
            ),
            'contract_renewal_cycles' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'contract_period_start' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'contract_period_end' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'snap_to_nth_day' => new Property(
                type: Type::INTEGER,
                null: true,
            ),

            /* Payment Source */

            'payment_source_id' => new Property(
                null: true,
                in_array: false,
            ),
            'payment_source_type' => new Property(
                null: true,
                validate: ['enum', 'choices' => ['card', 'bank_account']],
                in_array: false,
            ),

            /* Hidden Properties */

            'pending_renewal' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
            'canceled' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
            'finished' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
            'approval_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
                relation: SubscriptionApproval::class,
            ),
            'prorate' => new Property(
                type: Type::BOOLEAN,
                default: true,
                in_array: false,
            ),

            /* Computed Properties */

            // equivalent to current period start date
            'renewed_last' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            // equivalent to current period end date
            'renews_next' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'canceled_at' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'status' => new Property(),
            'num_invoices' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
            'recurring_total' => new Property(
                type: Type::FLOAT,
            ),
            'mrr' => new Property(
                type: Type::FLOAT,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([static::class, 'verifyActiveCustomer'], 1);
        self::saved([self::class, 'writeAddons']);

        parent::initialize();
    }

    protected function getMassAssignmentBlocked(): ?array
    {
        return ['canceled', 'finished', 'approval_id', 'renewed_last', 'canceled_at', 'status', 'mrr', 'recurring_total'];
    }

    //
    // Hooks
    //

    public static function writeAddons(AbstractEvent $event, string $eventName): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $isUpdate = ModelUpdated::getName() == $eventName;
        $model->saveAddons($isUpdate);
        $model->saveCoupons($isUpdate);
        $model->refreshPaymentSource = true;
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['url'] = $this->url;
        $approval = $this->approval;
        $result['approval'] = $approval ? $approval->toArray() : null;
        $paymentSource = $this->payment_source;
        $result['payment_source'] = $paymentSource ? $paymentSource->toArray() : null;
        $shipTo = $this->ship_to;
        $result['ship_to'] = $shipTo ? $shipTo->toArray() : null;
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

        // addons
        if (!isset($exclude['addons'])) {
            $expandCatalogItem = (bool) array_value($expand, 'addons.catalog_item');
            $expandPlan = (bool) array_value($expand, 'addons.plan');
            $result['addons'] = $this->addons($expandCatalogItem, $expandPlan);
        }

        // discounts
        if (!isset($exclude['discounts'])) {
            $result['discounts'] = array_map(fn (CouponRedemption $redemption) => $redemption->toArray(), $this->couponRedemptions());
        }

        // taxes
        if (!isset($exclude['taxes'])) {
            $result['taxes'] = TaxRate::expandList((array) $result['taxes']);
        }

        // customer name
        if (isset($include['customerName'])) {
            $result['customerName'] = $this->customer()->name;
        }
    }

    //
    // Accessors
    //

    /**
     * Generates the URL for this subscription.
     */
    protected function getUrlValue(): string
    {
        return $this->tenant()->url.'/subscriptions/'.$this->client_id;
    }

    /**
     * Gets the attached subscription approval.
     */
    protected function getApprovalValue(): ?SubscriptionApproval
    {
        return $this->relation('approval_id');
    }

    //
    // Mutators
    //

    /**
     * Sets the plan value.
     */
    protected function setPlanValue(?string $id): ?string
    {
        if (!$id || $id == $this->plan || ($this->_plan && $this->_plan->id == $id)) {
            return $id;
        }

        // lock in a plan to the current version
        // by fetching the internal ID of the given plan
        $plan = Plan::getCurrent($id);
        if (!$plan) {
            return null;
        }

        $this->_plan = $plan;
        $this->plan_id = $plan->internal_id;

        return $id;
    }

    //
    // Validators
    //

    /**
     * Validates the plan quantity.
     */
    public static function validateQuantity(mixed $quantity): bool
    {
        return is_numeric($quantity) && $quantity > 0;
    }

    /**
     * Validates a plan amount.
     */
    public static function validateAmount(mixed $amount): bool
    {
        return null === $amount || (is_numeric($amount) && $amount >= 0);
    }

    /**
     * Validates taxes input.
     */
    public static function validateTaxes(mixed $taxes): bool
    {
        if (!is_array($taxes)) {
            return false;
        }

        foreach ($taxes as $id) {
            if (!is_string($id) && !is_numeric($id)) {
                return false;
            }
        }

        return true;
    }

    //
    // Getters
    //

    public function relation(string $name): Model|null
    {
        if ('plan' == $name) {
            return $this->plan();
        }

        return parent::relation($name);
    }

    /**
     * Gets the plan for this subscription.
     *
     * @throws RuntimeException
     */
    public function plan(): Plan
    {
        if (!$this->_plan && $id = $this->plan_id) {
            $this->_plan = Plan::find($id);
        }

        if (!($this->_plan instanceof Plan)) {
            throw new RuntimeException('Plan not found');
        }

        return $this->_plan;
    }

    /**
     * Sets the plan.
     */
    public function setPlan(Plan $plan): void
    {
        $this->_plan = $plan;
        $this->plan_id = $plan->internal_id;
        $this->plan = $plan->id;
    }

    public function setCurrentBillingCycle(BillingPeriod $billingCycle): void
    {
        $this->period_start = $billingCycle->getStartDateTimestamp();
        $this->period_end = $billingCycle->getEndDateTimestamp();
        $this->renews_next = $billingCycle->getBillDateTimestamp();
    }

    public function clearCurrentBillingCycle(): void
    {
        $this->renews_next = null;
        $this->period_start = null;
        $this->period_end = null;
    }

    public function billingPeriods(): BillingPeriods
    {
        return new BillingPeriods($this);
    }

    public function billingMode(): BillingModeInterface
    {
        $this->tenant()->useTimezone();

        if ($this::BILL_IN_ADVANCE == $this->bill_in) {
            return new BillInAdvance((int) $this->bill_in_advance_days);
        }

        return new BillInArrears();
    }

    public function contractPeriods(): ContractPeriods
    {
        return new ContractPeriods($this);
    }

    /**
     * Gets the subscription addon objects.
     *
     * @return SubscriptionAddon[]
     */
    public function getAddons(): array
    {
        if (!is_array($this->_addons)) {
            return $this->loadAddons();
        }

        return $this->_addons;
    }

    /**
     * Sets the subscription addon objects, for testing.
     */
    public function setAddons(array $addons): void
    {
        $this->_addons = $addons;
    }

    /**
     * Gets the expanded subscription addons.
     */
    public function addons(bool $expandItem = false, bool $expandPlan = false): array
    {
        $result = [];
        foreach ($this->getAddons() as $addon) {
            $_addon = $addon->toArray();
            if ($expandItem && $item = $addon->item()) {
                $_addon['catalog_item'] = $item->toArray();
            }
            if ($expandPlan && $plan = $addon->plan()) {
                $_addon['plan'] = $plan->toArray();
            }
            $result[] = $_addon;
        }

        return $result;
    }

    /**
     * Loads the subscription addons.
     *
     * @return SubscriptionAddon[]
     */
    public function loadAddons(): array
    {
        if ($this->hasId()) {
            $this->_addons = SubscriptionAddon::queryWithTenant($this->tenant())
                ->where('subscription_id', $this)
                ->sort('id ASC')
                ->first(self::ADDON_LIMIT);
        } else {
            $this->_addons = [];
        }

        return $this->_addons;
    }

    public function setSaveAddons(array $addons): void
    {
        $this->_saveAddons = $addons;
    }

    /**
     * Sets the subscription coupon redemptions objects, for testing.
     */
    public function setCouponRedemptions(array $redemptions): void
    {
        $this->_couponRedemptions = $redemptions;
    }

    public function setSaveCoupons(array $coupons): void
    {
        $this->_saveCoupons = $coupons;
    }

    /**
     * Gets the subscription's coupons.
     *
     * @return CouponRedemption[]
     */
    public function couponRedemptions(): array
    {
        if (!is_array($this->_couponRedemptions)) {
            if (!$this->persisted()) {
                return [];
            }

            $this->_couponRedemptions = CouponRedemption::queryWithTenant($this->tenant())
                ->where('parent_type', ObjectType::Subscription->typeName())
                ->where('parent_id', $this)
                ->where('active', true)
                ->first(5);
        }

        return $this->_couponRedemptions;
    }

    //
    // Utility Functions
    //

    /**
     * Saves the given addons.
     */
    private function saveAddons(bool $isUpdate = false): void
    {
        if (!is_array($this->_saveAddons)) {
            return;
        }

        $addonObjs = [];
        $ids = [];

        foreach ($this->_saveAddons as $params) {
            $id = (isset($params['id']) && $isUpdate) ? $params['id'] : false;
            $addon = new SubscriptionAddon(['id' => $id]);

            if (isset($params['plan']) && $params['plan'] instanceof Plan) {
                $addon->setPlan($params['plan']);
                unset($params['plan']);
            }

            foreach ($params as $k => $v) {
                $addon->$k = $v;
            }

            if (!$id) {
                $addon->tenant_id = $this->tenant_id;
                $addon->subscription_id = (int) $this->id();
            }

            // update an addon
            if (!$addon->save()) {
                throw new ListenerException('Could not save addons: '.$addon->getErrors(), ['field' => 'addons']);
            }

            $ids[] = $addon->id();
            $addonObjs[] = $addon;
        }

        // remove the deleted addons when updating
        if ($isUpdate) {
            $query = self::getDriver()->getConnection(null)->createQueryBuilder()
                ->delete('SubscriptionAddons')
                ->andWhere('tenant_id = '.$this->tenant_id)
                ->andWhere('subscription_id = '.$this->id());

            // shield existing addons from delete query
            if (count($ids) > 0) {
                $in = implode(',', $ids);
                $query->andWhere("id NOT IN ($in)");
            }

            $query->executeStatement();
        }

        $this->_addons = $addonObjs;
        $this->_saveAddons = null;
    }

    /**
     * Saves the attached coupons.
     */
    private function saveCoupons(bool $isUpdate = false): void
    {
        if (!is_array($this->_saveCoupons)) {
            return;
        }

        $couponRedemptions = [];
        $existing = $isUpdate ? $this->couponRedemptions() : [];

        foreach ($this->_saveCoupons as $coupon) {
            if ($isUpdate) {
                // look up, and if found, do nothing
                $found = false;
                foreach ($existing as $redemption) {
                    if ($redemption->coupon == $coupon->id()) {
                        $found = $redemption;

                        break;
                    }
                }

                if ($found) {
                    $couponRedemptions[] = $found;

                    continue;
                }
            }

            $redemption = new CouponRedemption();
            $redemption->tenant_id = $this->tenant_id;
            $redemption->parent_type = ObjectType::Subscription->typeName();
            $redemption->parent_id = (int) $this->id();
            $redemption->setCoupon($coupon);

            // save it
            if (!$redemption->save()) {
                throw new ListenerException('Could not save coupon redemptions: '.$redemption->getErrors(), ['field' => 'discounts']);
            }

            $couponRedemptions[] = $redemption;
        }

        $this->_couponRedemptions = $couponRedemptions;
        $this->_saveCoupons = null;

        // when updating remove any coupon redemptions that were not created
        if ($isUpdate) {
            $this->removeDeletedCouponRedemptions();
        }
    }

    /**
     * Removes deleted coupon redemptions.
     */
    private function removeDeletedCouponRedemptions(): void
    {
        $query = self::getDriver()->getConnection(null)->createQueryBuilder()
            ->delete('CouponRedemptions')
            ->andWhere('tenant_id = '.$this->tenant_id)
            ->andWhere('parent_type = "'.ObjectType::Subscription->typeName().'"')
            ->andWhere('parent_id = '.$this->id());

        // shield saved coupon redemptions from delete query
        if ($this->_couponRedemptions) {
            $ids = [];
            foreach ($this->_couponRedemptions as $redemption) {
                $ids[] = $redemption->id();
            }

            $query->andWhere('id NOT IN ('.implode(',', $ids).')');
        }

        $query->executeStatement();
    }

    /**
     * Triggers a status update on this subscription relative
     * to an invoice.
     *
     * @throws ModelException
     *
     * @return bool true when the status was updated
     */
    public function updateStatus(Invoice $invoice): bool
    {
        if ($this->canceled) {
            return false;
        }

        // if the invoice has reached its final payment attempt
        // then need to handle this accordingly
        if ($this->isNonPayment($invoice)) {
            $action = $invoice->tenant()->subscription_billing_settings->after_subscription_nonpayment;

            // cancel the subscription
            if ('cancel' === $action) {
                try {
                    CancelSubscriptionFacade::get()->cancel($this, 'nonpayment', NotificationEventType::SubscriptionExpired);
                } catch (OperationException $e) {
                    throw new ModelException($e->getMessage());
                }

                return true;
            }
        }

        $statusBefore = $this->status;
        $statusAfter = (new SubscriptionStatus($this))->get();
        $this->status = $statusAfter;

        // set a non-property to ensure the update happens as the
        // status will be calculated in the model.updating hook
        if (!$this->save()) {
            throw new ModelException('Could not update subscription status: '.$this->getErrors());
        }

        return $statusAfter != $statusBefore;
    }

    /**
     * Updates the MRR and recurring total on the subscription.
     */
    public function updateMrr(): void
    {
        try {
            [$recurringTotal, $mrr] = (new MrrCalculator())->calculateForSubscription($this, true);
            $recurringTotalD = $recurringTotal->toDecimal();
            $mrrD = $mrr->toDecimal();
            if ($recurringTotalD != $this->recurring_total || $mrrD != $this->mrr) {
                $this->recurring_total = $recurringTotalD;
                $this->mrr = $mrrD;
                $this->save();
            }
        } catch (InvoiceCalculationException|TaxCalculationException) {
            // do nothing
        }
    }

    /**
     * Checks if an AutoPay invoice has reached nonpayment status. We consider
     * nonpayment meaning that AutoPay was enabled and all automated payment
     * attempts failed. If an AutoPay invoice is paid and then refunded, that
     * is not considered nonpayment.
     */
    private function isNonPayment(Invoice $invoice): bool
    {
        return $invoice->autopay &&
              !$invoice->paid &&
            InvoiceStatus::Pending->value != $invoice->status &&
               $invoice->attempt_count > 0 &&
              !$invoice->next_payment_attempt &&
               0 == Transaction::where('invoice', $invoice->id())
                   ->where('status', Transaction::STATUS_SUCCEEDED)
                   ->count();
    }

    /**
     * Generates the invoice line items for the base plan of the subscription.
     * NOTE: This does not generate line items for addons.
     *
     * @throws PricingException
     */
    public function planLineItems(): array
    {
        $plan = $this->plan();
        $items = (new PricingEngine())->price($plan, $this->quantity, $this->amount);

        if ($description = $this->description) {
            foreach ($items as &$item) {
                $item['description'] = trim($item['description']."\n".$description);
            }
        }

        return $items;
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
        return ModelNormalizer::toArray($this, expand: ['customer', 'plan']);
    }

    //
    // SendableDocumentInterface
    //

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new SubscriptionEmailVariables($this);
    }

    public function schemaOrgActions(): ?string
    {
        return null; // not used for subscriptions
    }

    public function getSendClientUrl(): ?string
    {
        return null;
    }

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        return null; // not used for subscriptions
    }
}
