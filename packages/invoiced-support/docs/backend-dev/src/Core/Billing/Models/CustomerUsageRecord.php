<?php

namespace App\Core\Billing\Models;

class CustomerUsageRecord extends AbstractUsageRecord
{
    const QUOTA_METRIC = 'customers';
    const OVERAGE_METRIC = 'customer';

    public function getTablename(): string
    {
        return 'CustomerVolumes';
    }

    public function getMetricName(): string
    {
        return 'Customer';
    }

    public function getMetricNamePlural(): string
    {
        return 'Customers';
    }
}
