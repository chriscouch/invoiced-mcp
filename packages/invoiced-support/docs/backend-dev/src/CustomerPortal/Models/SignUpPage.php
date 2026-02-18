<?php

namespace App\CustomerPortal\Models;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Item;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\Traits\HasClientIdTrait;
use App\Metadata\Models\CustomField;
use App\SalesTax\Models\TaxRate;
use App\SubscriptionBilling\Models\Plan;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $type
 * @property array       $plans
 * @property string|null $setup_fee
 * @property array       $taxes
 * @property bool        $has_quantity
 * @property int         $trial_period_days
 * @property int|null    $snap_to_nth_day
 * @property bool        $has_coupon_code
 * @property string|null $header_text
 * @property bool        $billing_address
 * @property bool        $shipping_address
 * @property array       $custom_fields
 * @property string|null $tos_url
 * @property string|null $thanks_url
 * @property bool        $allow_multiple_subscriptions
 * @property string      $url
 */
class SignUpPage extends MultitenantModel
{
    use AutoTimestamps;
    use HasClientIdTrait;

    const TYPE_RECURRING = 'recurring';
    const TYPE_AUTOPAY = 'autopay';

    private array $_addons;
    private ?Item $_setupFee;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'type' => new Property(
                validate: ['enum', 'choices' => ['recurring', 'autopay']],
                default: self::TYPE_RECURRING,
            ),

            /* Subscription Configuration */

            'plans' => new Property(
                type: Type::ARRAY,
            ),
            'setup_fee' => new Property(
                null: true,
            ),
            'taxes' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'has_quantity' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'trial_period_days' => new Property(
                type: Type::INTEGER,
                default: 0,
            ),
            'snap_to_nth_day' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'has_coupon_code' => new Property(
                type: Type::BOOLEAN,
            ),

            /* Form Options */

            'header_text' => new Property(
                null: true,
            ),
            'billing_address' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'shipping_address' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'custom_fields' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'tos_url' => new Property(
                null: true,
            ),
            'thanks_url' => new Property(
                null: true,
            ),

            /* Customer Portal Settings */

            'allow_multiple_subscriptions' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::saving([self::class, 'verifyPlans']);
        parent::initialize();
    }

    //
    // Hooks
    //

    public static function verifyPlans(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        /** @var Plan $plan */
        foreach ($model->plans() as $plan) {
            if (Plan::PRICING_CUSTOM != $plan->pricing_mode) {
                continue;
            }

            throw new ListenerException('Custom priced plans are not allowed on sign up pages', ['field' => 'plans']);
        }
    }

    //
    // Model Overrides
    //

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['url'] = $this->url;

        // expand relationships
        $result['plans'] = [];
        foreach ($this->plans() as $plan) {
            $result['plans'][] = $plan->toArray();
        }

        $result['taxes'] = $this->taxes();

        $result['custom_fields'] = [];
        foreach ($this->customFields() as $customField) {
            $result['custom_fields'][] = $customField->toArray();
        }

        if ($result['setup_fee'] && $item = $this->setupFee()) {
            $result['setup_fee'] = $item->toArray();
        }

        return $result;
    }

    //
    // Mutators
    //

    /**
     * Sets the setup_fee value.
     */
    protected function setSetupFeeValue(mixed $id): ?string
    {
        if (!$id || $id == $this->setup_fee) {
            return $id;
        }

        // lock in a catalog item to the current version
        // by fetching the internal ID of the given catalog item
        $item = Item::getCurrent($id);
        if (!$item) {
            return null;
        }

        $this->_setupFee = $item;

        return (string) $id;
    }

    //
    // Accessors
    //

    /**
     * Gets the url property.
     */
    protected function getUrlValue(): string
    {
        return $this->tenant()->url.'/pages/'.$this->client_id;
    }

    //
    // Getters
    //

    public function plans(): array
    {
        if (!is_array($this->plans)) {
            return [];
        }

        $plans = [];
        foreach ($this->plans as $id) {
            if ($plan = Plan::getCurrent($id)) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }

    public function taxes(): array
    {
        return TaxRate::expandList($this->taxes);
    }

    /**
     * Gets the custom fields associated with this sign up page.
     *
     * @return CustomField[]
     */
    public function customFields(): array
    {
        $result = [];
        foreach ($this->custom_fields as $id) {
            $fields = CustomField::where('id', $id)->all();
            foreach ($fields as $field) {
                if ((ObjectType::Customer->typeName() == $field->object && self::TYPE_AUTOPAY == $this->type) || (ObjectType::Subscription->typeName() == $field->object && self::TYPE_RECURRING == $this->type)) {
                    $result[] = $field;
                }
            }
        }

        return $result;
    }

    /**
     * Gets the sign up page addons.
     *
     * @return SignUpPageAddon[]
     */
    public function addons(): array
    {
        if (isset($this->_addons)) {
            return $this->_addons;
        }

        $this->_addons = SignUpPageAddon::where('sign_up_page_id', $this->id())
            ->sort('order ASC,id ASC')
            ->first(100);

        return $this->_addons;
    }

    /**
     * Gets the setup fee catalog item.
     */
    public function setupFee(): ?Item
    {
        if (!isset($this->_setupFee) && $id = $this->setup_fee) {
            $this->_setupFee = Item::getCurrent($id);
        }

        return $this->_setupFee;
    }

    /**
     * Gets the sign up URL for a specific customer.
     */
    public function customerUrl(Customer $customer): string
    {
        return $this->url.'/'.$customer->client_id;
    }

    public static function getClientIdExpiration(): int
    {
        // sign up pages do not expire
        return strtotime('+5 years');
    }

    /**
     * Sets the sign up page addons.
     */
    public function setAddons(array $_addons): self
    {
        $this->_addons = $_addons;

        return $this;
    }
}
