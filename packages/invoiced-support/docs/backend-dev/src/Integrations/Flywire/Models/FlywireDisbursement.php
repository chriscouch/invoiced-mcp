<?php

namespace App\Integrations\Flywire\Models;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use DateTimeInterface;

/**
 * @property int                $id
 * @property string             $disbursement_id
 * @property string             $status_text
 * @property string             $recipient_id
 * @property ?DateTimeInterface $delivered_at
 * @property string             $bank_account_number
 * @property float              $amount
 * @property string             $currency
 */
class FlywireDisbursement extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'disbursement_id' => new Property(
                required: true,
                validate: ['unique', 'column' => 'disbursement_id'],
            ),
            'status_text' => new Property(
                required: true,
            ),
            'recipient_id' => new Property(
                required: true,
            ),
            'delivered_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'bank_account_number' => new Property(
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
