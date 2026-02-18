<?php

namespace App\SubscriptionBilling\Models;

use App\AccountsReceivable\Models\Coupon;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * This model represents the application of a coupon to a
 * subscription or customer.
 *
 * @property int    $id
 * @property string $parent_type
 * @property int    $parent_id
 * @property string $coupon
 * @property int    $coupon_id
 * @property bool   $active
 * @property int    $num_uses
 */
class CouponRedemption extends MultitenantModel
{
    use AutoTimestamps;

    private ?Coupon $_coupon = null;

    protected static function getProperties(): array
    {
        return [
            'parent_type' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['enum', 'choices' => ['customer', 'subscription']],
            ),
            'parent_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'coupon' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'coupon_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'active' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'num_uses' => new Property(
                type: Type::INTEGER,
                default: 0,
            ),
        ];
    }

    public function toArray(): array
    {
        return $this->coupon()->toArray();
    }

    //
    // Setters
    //

    public function setCoupon(Coupon $coupon): void
    {
        $this->_coupon = $coupon;
        $this->coupon_id = $coupon->internal_id;
        $this->coupon = $coupon->id;
    }

    //
    // Getters
    //

    /**
     * Gets the coupon for this redemption.
     */
    public function coupon(): Coupon
    {
        if (!$this->_coupon) {
            $this->_coupon = Coupon::findOrFail($this->coupon_id);
        }

        return $this->_coupon;
    }
}
