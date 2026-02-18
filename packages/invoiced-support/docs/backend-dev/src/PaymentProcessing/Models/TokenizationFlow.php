<?php

namespace App\PaymentProcessing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;
use App\CustomerPortal\Models\SignUpPage;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Enums\TokenizationFlowStatus;
use DateTimeInterface;

/**
 * @property int                    $id
 * @property string                 $identifier
 * @property TokenizationFlowStatus $status
 * @property Customer|null          $customer
 * @property PaymentMethodType|null $payment_method
 * @property ObjectType|null        $payment_source_type
 * @property int|null               $payment_source_id
 * @property bool                   $make_payment_source_default
 * @property string|null            $return_url
 * @property string|null            $email
 * @property PaymentFlowSource      $initiated_from
 * @property SignUpPage|null        $sign_up_page
 * @property DateTimeInterface|null $completed_at
 * @property DateTimeInterface|null $canceled_at
 */
class TokenizationFlow extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'identifier' => new Property(
                required: true,
            ),
            'status' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: TokenizationFlowStatus::class,
            ),
            'customer' => new Property(
                null: true,
                belongs_to: Customer::class,
            ),
            'payment_method' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: PaymentMethodType::class,
            ),
            'payment_source_type' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: ObjectType::class,
            ),
            'payment_source_id' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'make_payment_source_default' => new Property(
                type: Type::BOOLEAN,
            ),
            'return_url' => new Property(
                null: true,
            ),
            'email' => new Property(
                null: true,
            ),
            'initiated_from' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: PaymentFlowSource::class,
            ),
            'sign_up_page' => new Property(
                null: true,
                belongs_to: SignUpPage::class,
            ),
            'completed_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'canceled_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }

    public function setPaymentSource(PaymentSource $paymentSource): void
    {
        $this->payment_source_type = ObjectType::fromModel($paymentSource);
        $this->payment_source_id = $paymentSource->id;
    }
}
