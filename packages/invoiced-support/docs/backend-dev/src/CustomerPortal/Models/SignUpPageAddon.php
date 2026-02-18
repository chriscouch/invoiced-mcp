<?php

namespace App\CustomerPortal\Models;

use App\AccountsReceivable\Models\Item;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\SubscriptionBilling\Models\Plan;

/**
 * Represents an addon to a sign up page that a customer can purchase.
 *
 * @property int    $id
 * @property int    $sign_up_page
 * @property int    $sign_up_page_id
 * @property string $catalog_item
 * @property string $catalog_item_id
 * @property string $plan
 * @property string $plan_id
 * @property string $type
 * @property bool   $required
 * @property bool   $recurring
 * @property int    $order
 */
class SignUpPageAddon extends MultitenantModel
{
    use AutoTimestamps;

    const TYPE_BOOLEAN = 'boolean';

    const TYPE_QUANTITY = 'quantity';

    protected static function getProperties(): array
    {
        return [
            'sign_up_page_id' => new Property(
                type: Type::INTEGER,
                required: true,
                in_array: false,
                relation: SignUpPage::class,
            ),
            'catalog_item_id' => new Property(
                null: true,
                in_array: false,
                relation: Item::class,
            ),
            'plan_id' => new Property(
                null: true,
                in_array: false,
                relation: Plan::class,
            ),
            'type' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['boolean', 'quantity']],
            ),
            'required' => new Property(
                type: Type::BOOLEAN,
            ),
            'recurring' => new Property(
                type: Type::BOOLEAN,
                required: true,
            ),
            'order' => new Property(
                type: Type::INTEGER,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::saving([self::class, 'verifyPlan']);
        parent::initialize();
    }

    //
    // Hooks
    //

    public static function verifyPlan(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $plan = $model->plan();
        if (!($plan instanceof Plan)) {
            return;
        }

        if (Plan::PRICING_CUSTOM == $plan->pricing_mode) {
            throw new ListenerException('Custom priced plans are not allowed on sign up pages', ['field' => 'plans']);
        }
    }

    //
    // Model Overrides
    //

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['sign_up_page'] = $this->sign_up_page;

        if ($item = $this->item()) {
            $result['catalog_item'] = $item->toArray();
        } else {
            $result['catalog_item'] = $this->catalog_item;
        }

        if ($plan = $this->plan()) {
            $result['plan'] = $plan->toArray();
        } else {
            $result['plan'] = $this->plan;
        }

        return $result;
    }

    //
    // Accessors
    //

    /**
     * Gets the sign_up_page property.
     */
    protected function getSignUpPageValue(): int
    {
        return $this->sign_up_page_id;
    }

    /**
     * Gets the catalog_item property.
     */
    protected function getCatalogItemValue(): ?string
    {
        return $this->catalog_item_id;
    }

    /**
     * Gets the plan property.
     */
    protected function getPlanValue(): ?string
    {
        return $this->plan_id;
    }

    //
    // Mutators
    //

    /**
     * Sets the sign_up_page property.
     *
     * @param int $id
     */
    protected function setSignUpPageValue($id): int
    {
        $id = (int) $id;
        $this->sign_up_page_id = $id;

        return $id;
    }

    /**
     * Sets the catalog_item property.
     *
     * @param string $id
     */
    protected function setCatalogItemValue($id): string
    {
        $id = (string) $id;
        $this->catalog_item_id = $id;

        return $id;
    }

    /**
     * Sets the plan property.
     *
     * @param string $id
     */
    protected function setPlanValue($id): string
    {
        $id = (string) $id;
        $this->plan_id = $id;

        return $id;
    }

    //
    // Setters
    //

    public function setPlan(Plan $plan): void
    {
        $this->plan_id = $plan->id;
    }

    //
    // Getters
    //

    /**
     * Gets the attached catalog item.
     */
    public function item(): ?Item
    {
        return Item::getCurrent($this->catalog_item_id);
    }

    /**
     * Gets the attached plan.
     */
    public function plan(): ?Plan
    {
        return Plan::getCurrent($this->plan_id);
    }
}
