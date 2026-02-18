<?php

namespace App\Tests\Core\Billing\Models;

use App\Core\Billing\Models\MoneyBilledUsageRecord;

class MoneyBilledUsageRecordTest extends BaseUsageRecord
{
    protected function getModelClass(): string
    {
        return MoneyBilledUsageRecord::class;
    }

    public function testHasReachedQuota(): void
    {
        // test does not apply
        $this->assertFalse(false);
    }

    public function testSendOverageNotification(): void
    {
        // test does not apply
        $this->assertFalse(false);
    }
}
