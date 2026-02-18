<?php

namespace App\Integrations\Flywire\Models;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                 $id
 * @property string              $payout_id
 * @property FlywireDisbursement $disbursement
 * @property FlywirePayment      $payment
 * @property string              $status_text
 * @property float               $amount
 * @property string              $currency
 */
class FlywirePayout extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'payout_id' => new Property(
                required: true,
                validate: ['unique', 'column' => 'payout_id'],
            ),
            'disbursement' => new Property(
                required: true,
                belongs_to: FlywireDisbursement::class,
            ),
            'payment' => new Property(
                required: true,
                belongs_to: FlywirePayment::class,
            ),
            'status_text' => new Property(
                required: true,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'currency' => new Property(
                required: true,
            ),
        ];
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
