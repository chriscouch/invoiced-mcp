<?php

namespace App\Integrations\Flywire\Models;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Integrations\Flywire\Enums\FlywireRefundBundleStatus;
use DateTimeInterface;

/**
 * @property int                       $id
 * @property string                    $bundle_id
 * @property string                    $recipient_id
 * @property FlywireRefundBundleStatus $status
 * @property DateTimeInterface         $initiated_at
 * @property string|null               $marked_for_approval
 * @property float                     $amount
 * @property string                    $currency
 * @property DateTimeInterface|null    $recipient_date
 * @property string|null               $recipient_bank_reference
 * @property string|null               $recipient_account_number
 * @property float|null                $recipient_amount
 * @property string|null               $recipient_currency
 */
class FlywireRefundBundle extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'bundle_id' => new Property(
                required: true,
                validate: ['unique', 'column' => 'bundle_id'],
            ),
            'recipient_id' => new Property(
                required: true,
            ),
            'status' => new Property(
                type: Type::ENUM,
                enum_class: FlywireRefundBundleStatus::class,
            ),
            'initiated_at' => new Property(
                type: Type::DATETIME,
                required: true,
            ),
            'marked_for_approval' => new Property(
                type: Type::BOOLEAN,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'currency' => new Property(
                required: true,
            ),
            'recipient_date' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'recipient_bank_reference' => new Property(
                null: true,
            ),
            'recipient_account_number' => new Property(
                null: true,
            ),
            'recipient_amount' => new Property(
                type: Type::FLOAT,
                null: true,
            ),
            'recipient_currency' => new Property(
                null: true,
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

    public function setRecipientAmount(Money $amount): void
    {
        $this->recipient_amount = $amount->toDecimal();
        $this->recipient_currency = $amount->currency;
    }

    public function getRecipientAmount(): ?Money
    {
        return $this->recipient_currency && $this->recipient_amount ? Money::fromDecimal($this->recipient_currency, $this->recipient_amount) : null;
    }
}
