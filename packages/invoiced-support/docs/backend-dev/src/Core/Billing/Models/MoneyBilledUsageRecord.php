<?php

namespace App\Core\Billing\Models;

class MoneyBilledUsageRecord extends AbstractUsageRecord
{
    const QUOTA_METRIC = 'billed';
    const OVERAGE_METRIC = 'billed';

    public function getTablename(): string
    {
        return 'BilledVolumes';
    }

    public function getMetricName(): string
    {
        return '$ Billed';
    }

    public function getMetricNamePlural(): string
    {
        return '$ Billed';
    }
}
