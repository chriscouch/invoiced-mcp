<?php

namespace App\AccountsPayable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int          $id
 * @property VendorCredit $vendor_credit
 * @property string       $description
 * @property float        $amount
 * @property int          $order
 */
class VendorCreditLineItem extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'vendor_credit' => new Property(
                in_array: false,
                belongs_to: VendorCredit::class,
            ),
            'description' => new Property(
                validate: ['string', 'max' => 255],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
            ),
            'order' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
        ];
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        unset($result['vendor_credit_id']);

        return $result;
    }
}
