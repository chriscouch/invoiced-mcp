<?php

namespace App\PaymentProcessing\Models;

use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use DateTimeInterface;

/**
 * A merchant account transaction represents money movement within the client's merchant account.
 *
 * @property int                            $id
 * @property MerchantAccount                $merchant_account
 * @property MerchantAccountTransactionType $type
 * @property string                         $reference
 * @property string                         $currency
 * @property float                          $amount
 * @property float                          $fee
 * @property array                          $fee_details
 * @property float                          $net
 * @property DateTimeInterface              $available_on
 * @property string                         $description
 * @property ObjectType|null                $source_type
 * @property int|null                       $source_id
 * @property Payout|null                    $payout
 * @property string|null                    $merchant_reference
 */
class MerchantAccountTransaction extends MultitenantModel
{
    use ApiObjectTrait;
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'merchant_account' => new Property(
                required: true,
                belongs_to: MerchantAccount::class,
            ),
            'type' => new Property(
                type: Type::ENUM,
                enum_class: MerchantAccountTransactionType::class,
            ),
            'reference' => new Property(
                required: true,
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
            'fee' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'fee_details' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'net' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'available_on' => new Property(
                type: Type::DATE,
                required: true,
            ),
            'description' => new Property(
                required: true,
            ),
            'source_type' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: ObjectType::class,
            ),
            'source_id' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'payout' => new Property(
                null: true,
                belongs_to: Payout::class,
            ),
            'merchant_reference' => new Property(
                null: true,
            ),
        ];
    }

    public function getAmount(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount);
    }

    public function getNet(): Money
    {
        return Money::fromDecimal($this->currency, $this->net);
    }

    public function getFee(): Money
    {
        return Money::fromDecimal($this->currency, $this->fee);
    }

    public function setSource(?Model $model): void
    {
        if ($model) {
            $this->source_type = ObjectType::fromModel($model);
            $this->source_id = (int) $model->id();
        }
    }
}
