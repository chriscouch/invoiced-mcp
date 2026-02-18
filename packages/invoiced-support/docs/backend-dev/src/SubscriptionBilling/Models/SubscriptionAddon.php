<?php

namespace App\SubscriptionBilling\Models;

use App\AccountsReceivable\Models\Item;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Libs\PricingEngine;

/**
 * A subscription addon is an additional line item added
 * to a base subscription plan. For example, an addon
 * might be additional user seats.
 *
 * @property int        $id
 * @property float|null $amount
 * @property int        $subscription_id
 * @property string     $catalog_item
 * @property int        $catalog_item_id
 * @property string     $plan
 * @property int        $plan_id
 * @property float      $quantity
 * @property string     $description
 */
class SubscriptionAddon extends MultitenantModel
{
    use ApiObjectTrait;
    use AutoTimestamps;

    private ?Item $_catalogItem = null;
    private ?Plan $_plan = null;

    protected static function getProperties(): array
    {
        return [
            'subscription_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                relation: Subscription::class,
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
            'quantity' => new Property(
                type: Type::FLOAT,
                default: 1,
            ),
            'description' => new Property(),
            'amount' => new Property(
                type: Type::FLOAT,
                null: true,
                validate: ['callable', 'fn' => [self::class, 'validateAmount']],
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([self::class, 'verifyPlan']);
        self::saving([self::class, 'verifySubscriptionAmount']);
    }

    public static function verifyPlan(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->catalog_item && !$model->plan) {
            throw new ListenerException('You must provide a plan to create an addon.', ['field' => 'plan']);
        }
    }

    /**
     * Verifies the amount property.
     */
    public static function verifySubscriptionAmount(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $plan = $model->plan();
        if (!($plan instanceof Plan)) {
            return;
        }

        if (Plan::PRICING_CUSTOM !== $plan->pricing_mode && null !== $model->amount) {
            throw new ListenerException('Amounts are only allowed when the plan has a custom pricing mode', ['field' => 'amount']);
        } elseif (Plan::PRICING_CUSTOM === $plan->pricing_mode && null === $model->amount) {
            throw new ListenerException('An amount is required when the subscription has a custom plan', ['field' => 'amount']);
        }
    }

    //
    // Validators
    //

    /**
     * Validates a plan amount.
     */
    public static function validateAmount(mixed $amount): bool
    {
        return null === $amount || (is_numeric($amount) && $amount >= 0);
    }

    //
    // Mutators
    //

    protected function setAmountValue(mixed $value): ?float
    {
        if (null !== $value) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Sets the quantity value.
     */
    protected function setQuantityValue(mixed $value): float
    {
        return (float) $value;
    }

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

        $this->_catalogItem = $item;
        $this->catalog_item_id = $item->internal_id;

        return $id;
    }

    /**
     * Sets the plan value.
     */
    protected function setPlanValue(mixed $id): ?string
    {
        if (!$id || $id == $this->plan || ($this->_plan && $this->_plan->id == $id)) {
            return $id;
        }

        // lock in a catalog item to the current version
        // by fetching the internal ID of the given catalog item
        $plan = Plan::getCurrent($id);
        if (!$plan) {
            return null;
        }

        $this->_plan = $plan;
        $this->plan_id = $plan->internal_id;

        return $id;
    }

    //
    // Setters
    //

    public function setPlan(Plan $plan): void
    {
        $this->_plan = $plan;
        $this->plan_id = $plan->internal_id;
        $this->plan = $plan->id;
    }

    //
    // Getters
    //

    /**
     * Gets the catalog item for this addon.
     */
    public function item(): ?Item
    {
        if (!$this->_catalogItem && $id = $this->catalog_item_id) {
            $this->_catalogItem = Item::find($id);
        }

        return $this->_catalogItem;
    }

    /**
     * Gets the plan for this addon.
     */
    public function plan(): ?Plan
    {
        if (!$this->_plan && $id = $this->plan_id) {
            $this->_plan = Plan::find($id);
        }

        return $this->_plan;
    }

    /**
     * Generates the invoice line items for this addon.
     *
     * @throws PricingException
     */
    public function lineItems(): array
    {
        if ($catalogItem = $this->item()) {
            $items = [$catalogItem->lineItem()];
            $items[0]['quantity'] = $this->quantity;
        } elseif ($plan = $this->plan()) {
            $items = (new PricingEngine())->price($plan, $this->quantity, $this->amount);
        } else {
            $items = [];
        }

        if ($description = $this->description) {
            foreach ($items as &$item) {
                $item['description'] = trim($item['description']."\n".$description);
            }
        }

        return $items;
    }
}
