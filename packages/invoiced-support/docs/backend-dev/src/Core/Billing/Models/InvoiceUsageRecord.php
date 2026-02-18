<?php

namespace App\Core\Billing\Models;

class InvoiceUsageRecord extends AbstractUsageRecord
{
    const QUOTA_METRIC = 'invoices';
    const OVERAGE_METRIC = 'invoice';

    public function getTablename(): string
    {
        return 'InvoiceVolumes';
    }

    public function getMetricName(): string
    {
        return 'Invoice';
    }

    public function getMetricNamePlural(): string
    {
        return 'Invoices';
    }
}
