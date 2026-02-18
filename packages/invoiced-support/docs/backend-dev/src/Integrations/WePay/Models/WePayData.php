<?php

namespace App\Integrations\WePay\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string                 $website
 * @property string                 $description
 * @property string                 $mcc
 * @property bool|null              $accept_debit_cards
 * @property DateTimeInterface|null $read_cursor
 * @property DateTimeInterface|null $last_synced
 * @property int|null               $credit_card_percent_fee
 * @property DateTimeInterface|null $fee_effective_date
 */
class WePayData extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'website' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'description' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'mcc' => new Property(
                type: Type::STRING,
                null: true,
                default: null,
            ),
            'accept_debit_cards' => new Property(
                type: Type::BOOLEAN,
                null: true,
                default: null,
            ),
            'credit_card_percent_fee' => new Property(
                type: Type::INTEGER,
                null: true,
                validate: ['range', 'min' => 255, 'max' => 400],
            ),
            'fee_effective_date' => new Property(
                type: Type::DATE,
                null: true,
            ),
            'read_cursor' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'last_synced' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }

    /**
     * Gets the credit card fee percent component as an integer.
     * Example: 290 is returned for 2.9% fee.
     */
    public function getCreditCardPercentFee(CarbonImmutable $date): int
    {
        $effectiveDate = new CarbonImmutable($this->fee_effective_date);
        if ($date->isAfter($effectiveDate) && $customFee = $this->credit_card_percent_fee) {
            // Ensure this is never below the buy rate of 2.55%
            return max(255, $customFee);
        }

        // Standard is 2.9%
        return 290;
    }

    /**
     * Gets the credit card fee fixed component in cents.
     */
    public function getCreditCardFixedFee(): int
    {
        // Standard is $0.30. Currently, this is not customizable.
        return 30;
    }
}
