<?php

namespace App\PaymentPlans\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\AccountsReceivable\Interfaces\ModelAgeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * This model represents a single installment within a payment plan.
 *
 * @property int   $id
 * @property int   $payment_plan_id
 * @property int   $date
 * @property float $amount
 * @property float $balance
 * @property int   $age
 */
class PaymentPlanInstallment extends MultitenantModel implements ModelAgeInterface
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'payment_plan_id' => new Property(
                type: Type::INTEGER,
                required: true,
                in_array: false,
                relation: PaymentPlan::class,
            ),
            'date' => new Property(
                type: Type::DATE_UNIX,
                required: true,
                validate: 'timestamp',
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
                validate: ['callable', 'fn' => [self::class, 'validateAmount']],
            ),
            'balance' => new Property(
                type: Type::FLOAT,
                required: true,
                validate: ['callable', 'fn' => [self::class, 'validateBalance']],
            ),
        ];
    }

    public static function validateAmount(float $amount): bool
    {
        return $amount > 0;
    }

    public static function validateBalance(float $balance): bool
    {
        return $balance >= 0;
    }

    public function getAgeValue(): int
    {
        return (int) floor((time() - $this->date) / 86400);
    }

    public function getPastDueAgeValue(): int
    {
        return $this->age;
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        unset($result['created_at']);

        return $result;
    }
}
