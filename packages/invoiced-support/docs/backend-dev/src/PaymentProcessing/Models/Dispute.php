<?php

namespace App\PaymentProcessing\Models;

use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\ModelNormalizer;
use App\PaymentProcessing\Enums\DisputeStatus;

/**
 * A dispute represents a chargeback created through a payment gateway.
 *
 * @property int                             $id
 * @property int                             $charge_id
 * @property Charge                          $charge
 * @property string                          $currency
 * @property float                           $amount
 * @property string                          $gateway
 * @property string                          $gateway_id
 * @property DisputeStatus                   $status
 * @property string                          $reason
 * @property string                          $defense_reason
 * @property MerchantAccountTransaction|null $merchant_account_transaction
 */
class Dispute extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventObjectTrait;

    protected static function getProperties(): array
    {
        return [
            'charge' => new Property(
                required: true,
                belongs_to: Charge::class,
            ),
            'currency' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'status' => new Property(
                type: Type::ENUM,
                required: true,
                default: DisputeStatus::Undefended,
                enum_class: DisputeStatus::class,
            ),
            'reason' => new Property(
                type: Type::STRING,
            ),
            'defense_reason' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'gateway' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'gateway_id' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'merchant_account_transaction' => new Property(
                null: true,
                belongs_to: MerchantAccountTransaction::class,
            ),
        ];
    }

    public function getAmount(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount);
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        $charge = $this->charge;

        $result = [
            ['customer', $charge->customer_id],
            ['charge', $charge->id()],
        ];

        if ($payment = $charge->payment_id) {
            $result[] = ['payment', $payment];
        }

        return $result;
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['charge.customer']);
    }
}
