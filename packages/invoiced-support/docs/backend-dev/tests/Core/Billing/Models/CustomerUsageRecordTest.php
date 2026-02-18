<?php

namespace App\Tests\Core\Billing\Models;

use App\Core\Billing\Models\CustomerUsageRecord;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\EntryPoint\CronJob\UpdateCurrentMonthUsage;

class CustomerUsageRecordTest extends BaseUsageRecord
{
    private function getJob(): UpdateCurrentMonthUsage
    {
        return self::getService('test.update_current_month_usage');
    }

    protected function getModelClass(): string
    {
        return CustomerUsageRecord::class;
    }

    public function testUpdateAll(): void
    {
        $n = $this->getJob()->updateAll(MonthBillingPeriod::fromTimestamp(time()));
        $this->assertEquals(1, $n);
    }

    public function testSendOverageNotification(): void
    {
        self::getService('test.tenant')->set(self::$company);

        $job = $this->getJob();

        // cannot send overage from past month
        $volume = CustomerUsageRecord::getOrCreate(self::$company, MonthBillingPeriod::fromTimestamp((int) strtotime('last day of previous month')));
        $pricingPlan = new UsagePricingPlan();
        $pricingPlan->threshold = 1;
        $pricingPlan->unit_price = 2;

        $job->sendOverageNotification($volume, $pricingPlan);

        $volume = CustomerUsageRecord::getOrCreate(self::$company, MonthBillingPeriod::now());

        $job->sendOverageNotification($volume, $pricingPlan);
        $this->assertEquals(date('Ym'), self::$company->refresh()->last_overage_notification);

        // cannot resend it for the month
        $job->sendOverageNotification($volume, $pricingPlan);
    }
}
