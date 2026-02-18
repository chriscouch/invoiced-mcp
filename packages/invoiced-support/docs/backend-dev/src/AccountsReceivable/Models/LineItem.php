<?php

namespace App\AccountsReceivable\Models;

use App\AccountsReceivable\Traits\HasDiscountsTrait;
use App\AccountsReceivable\Traits\HasTaxesTrait;
use App\ActivityLog\Libs\EventSpoolFacade;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Search\Libs\SearchFacade;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\WriteSync\AccountingWriteSpoolFacade;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Traits\MetadataTrait;
use App\Sending\Email\Libs\EmailSpoolFacade;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;

/**
 * This model represents a line item on a document.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property string|null $catalog_item
 * @property int|null    $catalog_item_id
 * @property string|null $type
 * @property float       $unit_cost
 * @property float       $quantity
 * @property float       $amount
 * @property bool        $taxable
 * @property bool        $discountable
 * @property int|null    $subscription_id
 * @property bool        $prorated
 * @property string|null $plan
 * @property int|null    $plan_id
 * @property int|null    $period_start
 * @property int|null    $period_end
 * @property int|null    $invoice_id
 * @property int|null    $customer_id
 * @property int|null    $estimate_id
 * @property int|null    $credit_note_id
 * @property int         $order
 */
class LineItem extends MultitenantModel implements MetadataModelInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use HasDiscountsTrait;
    use HasTaxesTrait;
    use MetadataTrait;

    protected static array $recalculateProperties = [
        'invoice_id',
        'customer_id',
        'estimate_id',
        'credit_note_id',
        'quantity',
        'unit_cost',
        'discountable',
        'discounts',
        'taxable',
        'taxes',
    ];

    private static array $emptyItem = [
        'type' => null,
        'name' => '',
        'description' => '',
        'quantity' => 1,
        'unit_cost' => 0,
        'discountable' => true,
        'discounts' => [],
        'taxable' => true,
        'taxes' => [],
    ];

    private bool $_amountChanged = false;
    protected bool $_noRecalculate = false;
    private ReceivableDocument|Customer|null $_parent = null;
    private ?Item $_item = null;
    private ?Plan $_plan = null;

    //
    // Model Overrides
    //

    protected static function getProperties(): array
    {
        return [
            'invoice_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'customer_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'credit_note_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'estimate_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'catalog_item' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
            ),
            'catalog_item_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'plan' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
            ),
            'plan_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'subscription_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
                relation: Subscription::class,
            ),
            'period_start' => new Property(
                type: Type::DATE_UNIX,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'period_end' => new Property(
                type: Type::DATE_UNIX,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'prorated' => new Property(
                type: Type::BOOLEAN,
                mutable: Property::MUTABLE_CREATE_ONLY,
                default: false,
                in_array: false,
            ),
            'type' => new Property(
                null: true,
            ),
            'name' => new Property(),
            'quantity' => new Property(
                type: Type::FLOAT,
                required: true,
                validate: 'numeric',
                default: 1,
            ),
            'unit_cost' => new Property(
                type: Type::FLOAT,
                required: true,
                validate: 'numeric',
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
                validate: 'numeric',
            ),
            'description' => new Property(),
            'discountable' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'taxable' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'order' => new Property(
                type: Type::INTEGER,
                required: true,
                validate: 'numeric',
                default: 1,
                in_array: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'verifyParent']);
        self::creating([self::class, 'preCreate']);
        self::deleting([self::class, 'preDelete']);
        self::created([self::class, 'writeAppliedRates']);
        self::updated([self::class, 'writeAppliedRates']);
        self::created([self::class, 'recalculateParent']);
        self::updated([self::class, 'recalculateParent']);
        self::deleted([self::class, 'recalculateParent']);

        self::updating([self::class, 'beforeUpdate'], -512);

        parent::initialize();
    }

    public function getTablename(): string
    {
        return 'LineItems';
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['metadata'] = $this->metadata;
        $this->toArrayHook($result, [], [], []);

        // plan line items do not have a `catalog_item` property
        // they have an extra `plan` property
        if (!$result['plan']) {
            unset($result['plan']);
        }

        // subscription line items have extra properties
        // line items are a subscription line item if they
        // have a subscription ID associated or a service period
        if ($this->subscription_id || ($this->period_start && $this->period_end)) {
            $result['subscription'] = $this->subscription_id;
            $result['period_start'] = $this->period_start;
            $result['period_end'] = $this->period_end;
            $result['prorated'] = $this->prorated;
        }

        return $result;
    }

    //
    // Hooks
    //

    /**
     * Rolls back a transaction.
     */
    private function rollback(): void
    {
        // clear any unwritten events / indexing operations
        EventSpoolFacade::get()->clear();
        SearchFacade::get()->clearIndexSpools();
        AccountingWriteSpoolFacade::get()->clear();
        EmailSpoolFacade::get()->clear();
    }

    public static function verifyParent(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if (!$model->customer_id && !$model->invoice_id && !$model->estimate_id && !$model->credit_note_id) {
            throw new ListenerException('Line item does not have an associated parent');
        }

        $parent = $model->parent();
        if ($parent instanceof Customer) {
            if (!$parent->active) {
                // the customer relationship implies that this is a pending line item
                throw new ListenerException('This cannot be created because the customer is inactive', ['field' => 'customer']);
            }
        }
    }

    /**
     * Validate line item parent.
     *
     * @throws ListenerException
     */
    public function validate(): void
    {
        $parent = $this->parent();
        if ($parent instanceof ReceivableDocument) {
            if (!$parent->isEditable()) {
                throw new ListenerException((string) $parent->getErrors());
            }
        }
    }

    /**
     * Sets up the line item before creating.
     */
    public static function preCreate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // calculate amount (unless already calculated)
        $model->_amountChanged = true;
        if (!$model->_noRecalculate) {
            $model->validate();
            // @TODO does not take into account rounding due to parent's currency
            $model->amount = $model->quantity * $model->unit_cost;
        }

        // format discounts + taxes
        foreach (['discounts', 'taxes'] as $k) {
            if (!is_array($model->$k)) {
                $model->$k = [];
            }
        }

        // ensure the applied rates get saved
        $model->_saveDiscounts = $model->discounts;
        $model->_saveTaxes = $model->taxes;

        // set the plan internal ID if not provided
        if (!$model->plan_id && $planId = $model->plan) {
            $plan = Plan::getCurrent($planId);
            if (!$plan) {
                throw new ListenerException('No such plan: '.$planId, ['field' => 'plan']);
            }
            $model->plan_id = $plan->internal_id;
            $model->_plan = $plan;

            // set the item to the plan item if not provided
            if (!$model->catalog_item && $item = $plan->item()) {
                $model->catalog_item = $item->id;
                $model->catalog_item_id = $item->internal_id;
                $model->_item = $item;
            }
        }

        // set the item internal ID if not provided
        if (!$model->catalog_item_id && $itemId = $model->catalog_item) {
            $item = Item::getCurrent($itemId);
            if (!$item) {
                throw new ListenerException('No such item: '.$itemId, ['field' => 'catalog_item']);
            }
            $model->catalog_item_id = $item->internal_id;
            $model->_item = $item;
        }
    }

    public static function beforeUpdate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // check to see if the invoice needs to be recalculated
        foreach (self::$recalculateProperties as $prop) {
            if ($model->dirty($prop)) {
                $model->_amountChanged = true;
            }
        }

        // calculate new amount
        if (!$model->_noRecalculate) {
            $model->validate();
            if ($model->_amountChanged) {
                // TODO does not take into account rounding due to parent's currency
                // or tax inclusive pricing
                $model->amount = $model->quantity * $model->unit_cost;
            }
        }

        // format discounts + taxes
        foreach (['discounts', 'taxes'] as $type) {
            if (!$model->dirty($type)) {
                $model->$type = $model->$type();
            }
        }

        // ensure the applied rates get saved
        $model->_saveDiscounts = $model->discounts;
        $model->_saveTaxes = $model->taxes;
    }

    /**
     * Sets up the line item for deletion.
     */
    public static function preDelete(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->_amountChanged = true;
        $model->validate();
    }

    /**
     * Recalculates the parent.
     */
    public static function recalculateParent(AbstractEvent $event, string $eventName): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // BUG refresh() has to be present here because for some reason
        // the model properties like $model->tenant_id are missing at this stage
        if (ModelCreated::getName() == $eventName) {
            $model->refresh();
        }

        if ($model->_amountChanged && !$model->_noRecalculate) {
            $parent = $model->parent();
            if ($parent && method_exists($parent, 'recalculate')) {
                if (!$parent->recalculate()) {
                    $model->rollback();
                    $field = ObjectType::fromModel($parent)->typeName();
                    throw new ListenerException('Could not save '.$field.': '.$parent->getErrors(), ['field' => $field]);
                }
            }
        }

        $model->_amountChanged = false;
        $model->_noRecalculate = false;
    }

    /**
     * Handles any post update tasks.
     */
    public static function writeAppliedRates(AbstractEvent $event, string $eventName): void
    {
        $isUpdate = ModelUpdated::getName() == $eventName;
        /** @var self $lineItem */
        $lineItem = $event->getModel();
        $lineItem->saveDiscounts($isUpdate);
        $lineItem->saveTaxes($isUpdate);
    }

    public function toArrayHook(array &$result, array $exclude, array $include, array $expand): void
    {
        if ($this->_noArrayHook) {
            $this->_noArrayHook = false;

            return;
        }

        // discount and taxes
        if (!isset($exclude['rates'])) {
            foreach (['discounts', 'taxes'] as $type) {
                $result[$type] = $this->$type();
            }
        }
    }

    //
    // Mutators
    //

    /**
     * Sets the catalog_item value.
     */
    protected function setCatalogItemValue(mixed $id): ?string
    {
        if (!$id || $id == $this->catalog_item) {
            return $id;
        }

        // lock in a catalog item to the current version
        // by fetching the internal ID of the given catalog item
        $item = Item::getCurrent($id);
        if (!$item) {
            return null;
        }

        $this->_item = $item;
        $this->catalog_item_id = $item->internal_id;

        return $id;
    }

    /**
     * Sets the subscription value.
     */
    protected function setSubscriptionValue(mixed $id): ?int
    {
        $this->subscription_id = $id;

        return $id;
    }

    //
    // Getters
    //

    /**
     * Gets the parent for this line item.
     */
    public function parent(): ReceivableDocument|Customer|null
    {
        if ($this->_parent) {
            return $this->_parent;
        }

        if ($id = $this->invoice_id) {
            $this->_parent = new Invoice(['id' => $id]);
        } elseif ($id = $this->estimate_id) {
            $this->_parent = new Estimate(['id' => $id]);
        } elseif ($id = $this->credit_note_id) {
            $this->_parent = new CreditNote(['id' => $id]);
        } elseif ($id = $this->customer_id) {
            $this->_parent = new Customer(['id' => $id]);
        }

        return $this->_parent;
    }

    /**
     * Gets the catalog item for this line item.
     */
    public function item(): ?Item
    {
        if (!$this->_item && $id = $this->catalog_item_id) {
            $this->_item = Item::find($id);
        }

        return $this->_item;
    }

    /**
     * Gets the plan for this line item.
     */
    public function plan(): ?Plan
    {
        if (!$this->_plan && $id = $this->plan_id) {
            $this->_plan = Plan::find($id);
        }

        return $this->_plan;
    }

    /**
     * Gets the subscription for this line item.
     */
    public function subscription(): ?Subscription
    {
        return $this->relation('subscription_id');
    }

    /**
     * Gets the subscription value.
     */
    protected function getSubscriptionValue(mixed $id): ?int
    {
        if (!$id) {
            return $this->subscription_id;
        }

        return $id;
    }

    //
    // Setters
    //

    /**
     * Sets the line item parent.
     */
    public function setParent(Model $parent): void
    {
        $this->invoice_id = null;
        $this->customer_id = null;
        $this->estimate_id = null;
        $this->credit_note_id = null;

        $k = ObjectType::fromModel($parent)->typeName().'_id';
        $this->$k = $parent->id();

        $this->_parent = $parent;
    }

    /**
     * The next save will not trigger a recalculation on the parent.
     *
     * @return $this
     */
    public function noRecalculate()
    {
        $this->_noRecalculate = true;

        return $this;
    }

    //
    // Helpers
    //

    /**
     * Sanitizes a line item. This means that it ensures all the
     * appropriate fields are filled in.
     *
     * Sanitized Line Items will have the following properties:
     * - type
     * - name
     * - description
     * - quantity
     * - unit_cost
     * - discounts
     * - taxes
     */
    public static function sanitize(array $item, ?Item $catalogItem = null): array
    {
        // if there is a catalog item fill the line with
        if ($catalogItem) {
            $item = array_replace($catalogItem->lineItem(), $item);
        }

        // fill any missing properties that remain
        $item = array_replace(self::$emptyItem, $item);

        // cast to preferable data types
        $item['quantity'] = (float) $item['quantity'];
        $item['unit_cost'] = (float) $item['unit_cost'];

        if (isset($item['catalog_item']) && !is_string($item['catalog_item'])) {
            $item['catalog_item'] = null;
        }

        // trim name and description
        $item['name'] = trim((string) $item['name']);
        $item['description'] = trim((string) $item['description']);

        return $item;
    }

    /**
     * Calculates the line item amount. Returns the zero-decimal
     * amount.
     */
    public static function calculateAmount(string $currency, array $item): Money
    {
        return Money::fromDecimal($currency, $item['quantity'] * $item['unit_cost']);
    }

    //
    // MetadataStorageInterface
    //

    public function getMetadataTablePrefix(): string
    {
        return 'LineItem';
    }
}
