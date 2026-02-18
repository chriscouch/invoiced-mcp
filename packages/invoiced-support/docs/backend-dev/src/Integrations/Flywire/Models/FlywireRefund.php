<?php

namespace App\Integrations\Flywire\Models;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Integrations\Flywire\Enums\FlywireRefundStatus;
use App\PaymentProcessing\Models\Refund;
use DateTimeInterface;

/**
 * @property int                      $id
 * @property string                   $refund_id
 * @property FlywireDisbursement|null $disbursement
 * @property string                   $bundle_id
 * @property float                    $amount
 * @property string                   $currency
 * @property FlywireRefundStatus      $status
 * @property string                   $recipient_id
 * @property DateTimeInterface        $initiated_at
 * @property FlywirePayment|null      $payment
 * @property float                    $amount_to
 * @property string                   $currency_to
 * @property FlywireRefundBundle|null $bundle
 * @property Refund|null              $ar_refund
 */
class FlywireRefund extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'refund_id' => new Property(
                required: true,
                validate: ['unique', 'column' => 'refund_id'],
            ),
            'disbursement' => new Property(
                belongs_to: FlywireDisbursement::class,
            ),
            'recipient_id' => new Property(
                required: true,
            ),
            'payment' => new Property(
                null: true,
                belongs_to: FlywirePayment::class,
            ),
            'initiated_at' => new Property(
                type: Type::DATETIME,
                required: true,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'currency' => new Property(
                required: true,
            ),
            'amount_to' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'currency_to' => new Property(
                required: true,
            ),
            'status' => new Property(
                type: Type::ENUM,
                enum_class: FlywireRefundStatus::class,
            ),
            'bundle' => new Property(
                null: true,
                belongs_to: FlywireRefundBundle::class,
            ),
            'ar_refund' => new Property(
                null: true,
                belongs_to: Refund::class
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

    public function setAmount(Money $amount): void
    {
        $this->amount = $amount->toDecimal();
        $this->currency = $amount->currency;
    }

    public function getAmount(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount);
    }
}
