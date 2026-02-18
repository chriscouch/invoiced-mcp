<?php

namespace App\Tests\Core\Billing\Models;

use App\Core\Billing\Models\InvoiceUsageRecord;

class InvoiceUsageRecordTest extends BaseUsageRecord
{
    protected function getModelClass(): string
    {
        return InvoiceUsageRecord::class;
    }

    public function testGetOrCreate(): void
    {
        self::$company->features->disable('no_invoices');
        parent::testGetOrCreate();
    }
}
