<?php

namespace App\AccountsReceivable\Models;

use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int        $id
 * @property string     $name
 * @property int|null   $due_in_days
 * @property bool|null  $discount_is_percent
 * @property float|null $discount_value
 * @property int|null   $discount_expires_in_days
 * @property bool       $active
 */
class PaymentTerms extends MultitenantModel
{
    use AutoTimestamps;
    use ApiObjectTrait;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
                validate: [
                    ['string', 'min' => 1, 'max' => 255],
                    ['unique', 'column' => 'name'],
                ],
            ),
            'due_in_days' => new Property(
                null: true,
                validate: ['range', 'min' => 0, 'max' => 999],
            ),
            'discount_is_percent' => new Property(
                type: Type::BOOLEAN,
                null: true,
            ),
            'discount_value' => new Property(
                type: Type::FLOAT,
                null: true,
                validate: ['callable', 'fn' => [self::class, 'validateDiscountValue']],
            ),
            'discount_expires_in_days' => new Property(
                type: Type::INTEGER,
                null: true,
                validate: ['range', 'min' => 1, 'max' => 999],
            ),
            'active' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
        ];
    }

    public static function validateDiscountValue(mixed $value): bool
    {
        return $value > 0;
    }

    /**
     * Checks if this payment terms have a due date.
     */
    public function hasDueDate(): bool
    {
        return null !== $this->due_in_days && $this->due_in_days >= 0;
    }

    /**
     * Checks if these payment terms have an early discount.
     */
    public function hasEarlyDiscount(): bool
    {
        return $this->discount_expires_in_days > 0 && $this->discount_value > 0;
    }

    /**
     * Gets the due date for these payment terms.
     *
     * @param int|null $date optional date to offset due date
     */
    public function getDueDate(?int $date = null): ?int
    {
        if (!$this->hasDueDate()) {
            return null;
        }

        if (null === $date) {
            $date = time();
        }

        return (int) strtotime('+'.$this->due_in_days.' days', $date);
    }

    /**
     * Gets the early discount for these payment terms.
     */
    public function getEarlyDiscount(Money $amount, ?int $now = null): ?array
    {
        if (!$this->hasEarlyDiscount()) {
            return null;
        }

        if (null === $now) {
            $now = time();
        }

        if ($this->discount_is_percent) {
            $discountAmount = $amount->toDecimal() * $this->discount_value / 100.0;
            $discountAmount = Money::fromDecimal($amount->currency, $discountAmount);
        } else {
            $discountAmount = Money::fromDecimal($amount->currency, (float) $this->discount_value);
        }

        return [
            'amount' => $discountAmount->toDecimal(),
            'expires' => strtotime('+'.$this->discount_expires_in_days.' days', $now),
        ];
    }
}
