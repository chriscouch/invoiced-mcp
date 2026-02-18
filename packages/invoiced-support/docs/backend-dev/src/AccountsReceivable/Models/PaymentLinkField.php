<?php

namespace App\AccountsReceivable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;

/**
 * @property int         $id
 * @property PaymentLink $payment_link
 * @property ObjectType  $object_type
 * @property string      $custom_field_id
 * @property bool        $required
 * @property int         $order
 */
class PaymentLinkField extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'payment_link' => new Property(
                belongs_to: PaymentLink::class,
            ),
            'object_type' => new Property(
                type: Type::ENUM,
                enum_class: ObjectType::class,
            ),
            'custom_field_id' => new Property(),
            'required' => new Property(
                type: Type::BOOLEAN,
            ),
            'order' => new Property(
                type: Type::INTEGER,
            ),
        ];
    }

    public function getFormId(): string
    {
        return $this->object_type->typeName().'__'.$this->custom_field_id;
    }

    /**
     * @return self[]
     */
    public static function getForObjectType(PaymentLink $paymentLink, ObjectType $objectType): array
    {
        return PaymentLinkField::where('payment_link_id', $paymentLink)
            ->where('object_type', $objectType->value)
            ->first(100);
    }
}
