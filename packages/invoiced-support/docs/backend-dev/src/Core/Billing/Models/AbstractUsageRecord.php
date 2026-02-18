<?php

namespace App\Core\Billing\Models;

use App\Companies\Models\Company;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int  $month
 * @property int  $count
 * @property bool $do_not_bill
 */
abstract class AbstractUsageRecord extends MultitenantModel
{
    const QUOTA_METRIC = '';
    const OVERAGE_METRIC = '';

    protected static function getIDProperties(): array
    {
        return ['tenant_id', 'month'];
    }

    protected static function getProperties(): array
    {
        return [
            'month' => new Property(
                type: Type::INTEGER,
            ),
            'count' => new Property(
                type: Type::INTEGER,
            ),
            'do_not_bill' => new Property(
                type: Type::BOOLEAN,
            ),
        ];
    }

    /**
     * Attempts to retrieve an BillingUsage model for a company.
     */
    public static function getOrCreate(Company $company, MonthBillingPeriod $billingPeriod): static
    {
        $usageRecord = static::queryWithTenant($company)
            ->where('month', $billingPeriod->getName())
            ->oneOrNull();

        if ($usageRecord instanceof static) {
            return $usageRecord;
        }

        $usageRecord = new static(); /* @phpstan-ignore-line */
        $usageRecord->tenant_id = (int) $company->id();
        $usageRecord->month = (int) $billingPeriod->getName();
        $usageRecord->count = 0;
        $usageRecord->saveOrFail();

        return $usageRecord;
    }

    abstract public function getMetricName(): string;

    abstract public function getMetricNamePlural(): string;
}
