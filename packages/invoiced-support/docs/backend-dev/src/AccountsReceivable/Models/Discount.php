<?php

namespace App\AccountsReceivable\Models;

use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * This represents a line item or subtotal discount.
 *
 * @property int|null $expires
 */
class Discount extends AppliedRate
{
    const RATE_MODEL = Coupon::class;

    protected static function getProperties(): array
    {
        return [
            'expires' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'from_payment_terms' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }

    public function isExpirable(): bool
    {
        return null !== $this->expires;
    }
}
