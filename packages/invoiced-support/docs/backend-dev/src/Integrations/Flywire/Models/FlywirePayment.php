<?php

namespace App\Integrations\Flywire\Models;

use App\CashApplication\Models\Payment;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Integrations\Flywire\Enums\FlywirePaymentStatus;
use App\PaymentProcessing\Models\MerchantAccount;
use DateTimeInterface;

/**
 * @property int                    $id
 * @property MerchantAccount        $merchant_account
 * @property DateTimeInterface      $initiated_at
 * @property string                 $payment_id
 * @property string                 $recipient_id
 * @property float                  $amount_from
 * @property float                  $amount_to
 * @property float                  $surcharge_percentage
 * @property string                 $currency_from
 * @property string                 $currency_to
 * @property FlywirePaymentStatus   $status
 * @property DateTimeInterface|null $expiration_date
 * @property string|null            $payment_method_type
 * @property string|null            $payment_method_brand
 * @property string|null            $payment_method_card_classification
 * @property string|null            $payment_method_card_expiration
 * @property string|null            $payment_method_last4
 * @property string|null            $cancellation_reason
 * @property string|null            $reason
 * @property string|null            $reason_code
 * @property Payment|null           $ar_payment
 * @property string|null            $reference
 */
class FlywirePayment extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'payment_id' => new Property(
                required: true,
                validate: ['unique', 'column' => 'payment_id'],
            ),
            'recipient_id' => new Property(
                required: true,
            ),
            'initiated_at' => new Property(
                type: Type::DATETIME,
                required: true,
            ),
            'amount_from' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'amount_to' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'surcharge_percentage' => new Property(
                type: Type::FLOAT,
                required: false,
                default: 0.00
            ),
            'currency_from' => new Property(
                required: true,
            ),
            'currency_to' => new Property(
                required: true,
            ),
            'status' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: FlywirePaymentStatus::class,
            ),
            'expiration_date' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'payment_method_type' => new Property(
                null: true,
            ),
            'payment_method_brand' => new Property(
                null: true,
            ),
            'payment_method_card_classification' => new Property(
                null: true,
            ),
            'payment_method_card_expiration' => new Property(
                null: true,
            ),
            'payment_method_last4' => new Property(
                null: true,
            ),
            'cancellation_reason' => new Property(
                null: true,
            ),
            'reason' => new Property(
                null: true,
            ),
            'reason_code' => new Property(
                null: true,
            ),
            'ar_payment' => new Property(
                null: true,
                belongs_to: Payment::class,
            ),
            'merchant_account' => new Property(
                belongs_to: MerchantAccount::class,
            ),
            'reference' => new Property(
                null: true,
            ),
        ];
    }

    public function setAmountTo(Money $amount): void
    {
        $this->amount_to = $amount->toDecimal();
        $this->currency_to = $amount->currency;
    }

    public function getAmountTo(): Money
    {
        return Money::fromDecimal($this->currency_to, $this->amount_to);
    }

    public function setAmountFrom(Money $amount): void
    {
        $this->amount_from = $amount->toDecimal();
        $this->currency_from = $amount->currency;
    }

    public function getAmountFrom(): Money
    {
        return Money::fromDecimal($this->currency_from, $this->amount_from);
    }

    public function setSurchargePercentage(float $surchargePercentage): void
    {
        $this->surcharge_percentage = $surchargePercentage;
    }

    public function getSurchargePercentage(): float
    {
        return $this->surcharge_percentage;
    }
}
