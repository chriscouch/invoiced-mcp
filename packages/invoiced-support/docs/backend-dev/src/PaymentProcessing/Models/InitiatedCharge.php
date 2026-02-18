<?php

namespace App\PaymentProcessing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int              $id
 * @property string           $correlation_id
 * @property string           $currency
 * @property float            $amount
 * @property string           $gateway
 * @property int              $updated_at
 * @property int              $created_at
 * @property object           $parameters
 * @property object           $charge
 * @property string           $application_source
 * @property ?int             $source_id
 * @property ?int             $source_type
 * @property Customer         $customer
 * @property ?PaymentSource   $source
 * @property ?MerchantAccount $merchant_account
 * @property ?int             $merchant_account_id
 */
class InitiatedCharge extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'correlation_id' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'gateway' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'currency' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'parameters' => new Property(
                type: Type::OBJECT,
                default: [],
            ),
            'charge' => new Property(
                type: Type::OBJECT,
            ),
            'application_source' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'source' => new Property(
                null: true,
                belongs_to: PaymentSource::class,
            ),
            'customer' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Customer::class,
            ),
            'merchant_account' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                belongs_to: MerchantAccount::class,
            ),
        ];
    }

    public function setCharge(ChargeValueObject $charge): void
    {
        $this->charge = (object) [
            'customer' => $charge->customer->id,
            'amount' => (object) [
                'currency' => $charge->amount->currency,
                'amount' => $charge->amount->toDecimal(),
            ],
            'timestamp' => $charge->timestamp,
            'gateway' => $charge->gateway,
            'id' => $charge->gatewayId,
            'method' => $charge->method,
            'status' => $charge->status,
            'source' => $charge->source?->id,
            'failureReason' => $charge->failureReason,
            'sourceType' => $charge->source ? $charge->source::class : null,
        ];

        $this->save();
    }

    public function getCharge(): ChargeValueObject
    {
        $charge = $this->charge;

        if (!isset($charge->timestamp) || !isset($charge->gateway) || !isset($charge->id) || !isset($charge->method) || !isset($charge->status)) {
            throw new \RuntimeException('Charge is not fully initialized');
        }

        return new ChargeValueObject(
            customer: $this->customer,
            amount: Money::fromDecimal($this->currency, $this->amount),
            gateway: $charge->gateway,
            gatewayId: $charge->id,
            method: $charge->method,
            status: $charge->status,
            merchantAccount: $this->merchant_account,
            source: $charge->source && isset($charge->sourceType) && $charge->sourceType ? $charge->sourceType::find($charge->source) : null,
            description: '',
            timestamp: $charge->timestamp,
        );
    }
}
