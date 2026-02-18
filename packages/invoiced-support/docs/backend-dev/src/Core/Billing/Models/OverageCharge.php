<?php

namespace App\Core\Billing\Models;

use App\Core\Billing\Enums\BillingInterval;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string      $month
 * @property string      $dimension
 * @property int         $quantity
 * @property float       $price
 * @property float       $total
 * @property bool        $billed
 * @property string      $billing_system
 * @property string|null $billing_system_id
 * @property string|null $failure_message
 */
class OverageCharge extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'month' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
            ),
            'dimension' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
            ),
            'quantity' => new Property(
                type: Type::INTEGER,
            ),
            'price' => new Property(
                type: Type::FLOAT,
            ),
            'total' => new Property(
                type: Type::FLOAT,
            ),
            'billed' => new Property(
                type: Type::BOOLEAN,
            ),
            'billing_system' => new Property(
                type: Type::STRING,
            ),
            'billing_system_id' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'failure_message' => new Property(
                type: Type::STRING,
                null: true,
            ),
        ];
    }

    public function getPrice(): Money
    {
        return Money::fromDecimal('usd', $this->price);
    }

    public function getTotal(): Money
    {
        return Money::fromDecimal('usd', $this->total);
    }

    public function getDescription(): string
    {
        $monthStr = $this->getPeriodStart()->format('F Y');

        return $this->tenant()->name." usage for $monthStr";
    }

    public function getBillingInterval(): BillingInterval
    {
        // Currently the only supported billing interval for overages is monthly
        return BillingInterval::Monthly;
    }

    public function getPeriodStart(): CarbonImmutable
    {
        $date = new CarbonImmutable('now', new CarbonTimeZone('America/Chicago'));
        $year = (int) substr($this->month, 0, 4);
        $month = (int) substr($this->month, 4, 2);

        return $date->setDate($year, $month, 1)
            ->startOfMonth();
    }

    public function getPeriodEnd(): CarbonImmutable
    {
        $date = new CarbonImmutable('now', new CarbonTimeZone('America/Chicago'));
        $year = (int) substr($this->month, 0, 4);
        $month = (int) substr($this->month, 4, 2);

        return $date->setDate($year, $month, 1)
            ->endOfMonth();
    }
}
