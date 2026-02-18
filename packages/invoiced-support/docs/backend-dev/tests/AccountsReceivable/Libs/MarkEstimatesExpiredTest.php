<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\Core\Cron\ValueObjects\Run;
use App\EntryPoint\CronJob\MarkEstimatesExpired;
use App\Tests\AppTestCase;

class MarkEstimatesExpiredTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();

        // create an estimate that is past due
        $estimate = new Estimate();
        $estimate->setCustomer(self::$customer);
        $estimate->items = [['unit_cost' => 100]];
        $estimate->expiration_date = time() - 1;
        $estimate->saveOrFail();
        self::$estimate = $estimate;

        // hack to unmark the estimate as past due
        self::getService('test.database')->update('Estimates', ['status' => EstimateStatus::NOT_SENT], ['id' => $estimate->id()]);
    }

    public function testGetCompanies(): void
    {
        $job = $this->getJob();
        $companies = $job->getCompanies();
        $this->assertTrue(in_array(self::$company->id, $companies));
    }

    public function testGetDocuments(): void
    {
        $job = $this->getJob();
        $estimates = $job->getDocuments(self::$company);

        // verify that estimate is returned
        $this->assertCount(1, $estimates);
        $this->assertInstanceOf(Estimate::class, $estimates[0]);
        $this->assertEquals(self::$estimate->id(), $estimates[0]->id());
    }

    /**
     * @depends testGetDocuments
     */
    public function testExecute(): void
    {
        $job = $this->getJob();
        $job->execute(new Run());
        $this->assertEquals(1, $job->getTaskCount());
        $this->assertEquals(EstimateStatus::EXPIRED, self::$estimate->refresh()->status);
    }

    private function getJob(): MarkEstimatesExpired
    {
        return self::getService('test.mark_estimates_expired');
    }
}
