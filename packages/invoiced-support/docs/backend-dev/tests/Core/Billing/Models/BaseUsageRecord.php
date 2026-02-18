<?php

namespace App\Tests\Core\Billing\Models;

use App\Core\Billing\Models\AbstractUsageRecord;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Tests\AppTestCase;

abstract class BaseUsageRecord extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    abstract protected function getModelClass(): string;

    public function testMonthFromTime(): void
    {
        $this->assertEquals('201508', MonthBillingPeriod::fromTimestamp((int) mktime(0, 0, 0, 8, 25, 2015))->getName());
        $this->assertEquals('201412', MonthBillingPeriod::fromTimestamp((int) mktime(23, 59, 59, 12, 31, 2014))->getName());
        $this->assertEquals(date('Ym'), MonthBillingPeriod::now()->getName());
    }

    public function testGetOrCreate(): void
    {
        /** @var AbstractUsageRecord $model */
        $model = $this->getModelClass();

        for ($i = 0; $i < 3; ++$i) {
            $volume = $model::getOrCreate(self::$company, MonthBillingPeriod::now());

            $this->assertInstanceOf($model, $volume); /* @phpstan-ignore-line */
            $this->assertEquals(self::$company->id(), $volume->tenant_id);
            $this->assertEquals(0, $volume->count);
            $this->assertEquals(date('Ym'), $volume->month);
        }
    }
}
