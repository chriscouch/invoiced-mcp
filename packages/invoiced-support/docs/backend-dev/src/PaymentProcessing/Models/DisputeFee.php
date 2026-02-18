<?php

namespace App\PaymentProcessing\Models;

use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * A fee applied on merchant when chargeback is created.
 *
 * @property int     $id
 * @property string  $gateway_id
 * @property Dispute $dispute
 * @property string  $currency
 * @property float   $amount
 * @property string  $reason
 * @property bool    $success
 */
class DisputeFee extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'gateway_id' => new Property(
                type: Type::STRING,
            ),
            'dispute' => new Property(
                required: true,
                belongs_to: Dispute::class,
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
            'reason' => new Property(
                type: Type::STRING,
            ),
            'success' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }

    public function getAmount(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount);
    }
}
