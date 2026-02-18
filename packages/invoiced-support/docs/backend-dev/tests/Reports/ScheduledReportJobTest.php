<?php

namespace App\Tests\Reports;

use App\Companies\Models\Member;
use App\Core\Cron\ValueObjects\Run;
use App\EntryPoint\CronJob\ScheduledReportJob;
use App\Reports\Models\SavedReport;
use App\Reports\Models\ScheduledReport;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;

class ScheduledReportJobTest extends AppTestCase
{
    private static SavedReport $savedReport;
    private static ScheduledReport $scheduledReport1;
    private static ScheduledReport $scheduledReport2;
    private static ScheduledReport $scheduledReport3;
    private static ?Model $originalRequester;
    private static Member $requester;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$originalRequester = ACLModelRequester::get();
        self::$requester = Member::one();
        ACLModelRequester::set(self::$requester);

        self::$savedReport = new SavedReport();
        self::$savedReport->name = 'Report name';
        self::$savedReport->definition = '{"version":1,"title":"My Report","sections":[{"title":"Section 1","object":"invoice","type":"chart","chart_type":"bar","multi_entity":false,"fields":[{"field":{"id":"customer.name"}},{"field":{"function":"sum","arguments":[{"id":"balance"}]}}],"filter":[{"operator":">","value":"0","field":{"id":"balance"}}],"group":[{"field":{"id":"customer.name"},"name":null,"expanded":false}],"sort":[]}]}';
        self::$savedReport->saveOrFail();

        self::$scheduledReport1 = new ScheduledReport();
        self::$scheduledReport1->member = self::$requester;
        self::$scheduledReport1->saved_report = self::$savedReport;
        self::$scheduledReport1->time_of_day = 7;
        self::$scheduledReport1->run_date = 1;
        self::$scheduledReport1->parameters = [];
        self::$scheduledReport1->saveOrFail();
        self::$scheduledReport1->next_run = CarbonImmutable::now()->subDay();
        self::$scheduledReport1->saveOrFail();

        self::$scheduledReport2 = new ScheduledReport();
        self::$scheduledReport2->member = self::$requester;
        self::$scheduledReport2->saved_report = self::$savedReport;
        self::$scheduledReport2->time_of_day = 7;
        self::$scheduledReport2->run_date = 1;
        self::$scheduledReport2->parameters = [];
        self::$scheduledReport2->saveOrFail();

        self::$scheduledReport3 = new ScheduledReport();
        self::$scheduledReport3->member = self::$requester;
        self::$scheduledReport3->saved_report = self::$savedReport;
        self::$scheduledReport3->frequency = 'day_of_month';
        self::$scheduledReport3->time_of_day = 7;
        self::$scheduledReport3->run_date = 8;
        self::$scheduledReport3->parameters = [];
        self::$scheduledReport3->saveOrFail();
        self::$scheduledReport3->next_run = CarbonImmutable::now()->subDay();
        self::$scheduledReport3->saveOrFail();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ACLModelRequester::set(self::$originalRequester);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::getService('test.tenant')->set(self::$company);
    }

    private function getJob(): ScheduledReportJob
    {
        return self::getService('test.scheduled_reports_job');
    }

    public function testGetScheduledReports(): void
    {
        $scheduled = $this->getJob()->getTasks();
        if (!is_array($scheduled)) {
            $scheduled = iterator_to_array($scheduled);
        }
        $this->assertCount(2, $scheduled);
        $this->assertEquals(self::$scheduledReport1->id(), $scheduled[0]->id());
        $this->assertEquals(self::$scheduledReport3->id(), $scheduled[1]->id());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testExecute(): void
    {
        $this->getJob()->execute(new Run());
    }
}
