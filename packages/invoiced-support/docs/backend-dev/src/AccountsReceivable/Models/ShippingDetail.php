<?php

namespace App\AccountsReceivable\Models;

use App\Core\I18n\Countries;
use App\Core\Multitenant\Models\MultitenantModel;
use App\SubscriptionBilling\Models\Subscription;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property int|null    $invoice_id
 * @property int|null    $estimate_id
 * @property int|null    $subscription_id
 * @property string|null $name
 * @property string|null $attention_to
 * @property string|null $address1
 * @property string|null $address2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 */
class ShippingDetail extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                type: Type::INTEGER,
                mutable: Property::IMMUTABLE,
                in_array: false,
            ),
            'invoice_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
                relation: Invoice::class,
            ),
            'estimate_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
                relation: Estimate::class,
            ),
            'subscription_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
                relation: Subscription::class,
            ),
            'name' => new Property(
                null: true,
            ),
            'attention_to' => new Property(
                null: true,
            ),
            'address1' => new Property(
                null: true,
            ),
            'address2' => new Property(
                null: true,
            ),
            'city' => new Property(
                null: true,
            ),
            'state' => new Property(
                null: true,
            ),
            'postal_code' => new Property(
                null: true,
            ),
            'country' => new Property(
                null: true,
                validate: ['callable', 'fn' => [Countries::class, 'validateCountry']],
            ),
        ];
    }

    /**
     * Makes a new copy of this model that can be
     * saved as a new shipping address.
     */
    public function makeCopy(): self
    {
        $shipping = new self();
        foreach ($this->toArray() as $k => $v) {
            if ('created_at' == $k || 'id' == $k || str_contains($k, '_id')) {
                continue;
            }
            $shipping->$k = $v;
        }

        return $shipping;
    }

    /**
     * Gets the parent object of the address.
     */
    public function parent(): ?Model
    {
        if ($this->subscription_id) {
            return $this->relation('subscription_id');
        } elseif ($this->invoice_id) {
            return $this->relation('invoice_id');
        } elseif ($this->estimate_id) {
            return $this->relation('estimate_id');
        }

        return null;
    }
}
