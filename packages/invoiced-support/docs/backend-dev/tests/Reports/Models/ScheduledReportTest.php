<?php

namespace App\Tests\Reports\Models;

use App\Companies\Models\Member;
use App\Reports\Models\SavedReport;
use App\Reports\Models\ScheduledReport;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;

class ScheduledReportTest extends AppTestCase
{
    private static SavedReport $savedReport;
    private static ScheduledReport $scheduledReport;
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
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ACLModelRequester::set(self::$originalRequester);
    }

    public function testCreate(): void
    {
        self::$scheduledReport = new ScheduledReport();
        self::$scheduledReport->saved_report = self::$savedReport;
        self::$scheduledReport->time_of_day = 7;
        self::$scheduledReport->run_date = 1;
        self::$scheduledReport->parameters = ['$currency' => 'usd'];
        $this->assertTrue(self::$scheduledReport->save());

        $this->assertEquals(self::$company->id(), self::$scheduledReport->tenant_id);
        $this->assertEquals(self::$requester->id(), self::$scheduledReport->member_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $reports = SavedReport::all();
        $this->assertCount(1, $reports);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$scheduledReport->id,
            'frequency' => 'day_of_week',
            'last_run' => self::$scheduledReport->last_run,
            'next_run' => self::$scheduledReport->next_run,
            'parameters' => ['$currency' => 'usd'],
            'run_date' => 1,
            'time_of_day' => 7,
            'member' => self::$requester->user,
            'saved_report_id' => self::$savedReport->id,
            'member_id' => self::$requester->id,
            'created_at' => self::$scheduledReport->created_at,
            'updated_at' => self::$scheduledReport->updated_at,
        ];

        $this->assertEquals($expected, self::$scheduledReport->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        // Set the clock into the future to simulate modifying a scheduled report
        // when the next run is in the past.
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMonth());

        // When modifying a property that does not affect the schedule
        // the next run value SHOULD NOT change.
        $originalNextRun = self::$scheduledReport->next_run;
        self::$scheduledReport->last_run = CarbonImmutable::now();
        $this->assertTrue(self::$scheduledReport->save());
        $this->assertEquals($originalNextRun, self::$scheduledReport->next_run);

        // When modifying a schedule property the next run value SHOULD change.
        self::$scheduledReport->time_of_day = 8;
        $this->assertTrue(self::$scheduledReport->save());
        $this->assertNotEquals($originalNextRun, self::$scheduledReport->next_run);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$scheduledReport->delete());
    }

    public function testGetParameters(): void
    {
        $report = new ScheduledReport();
        $now = CarbonImmutable::now();
        $parametersToTest = [
            [['$dateRange' => ['test' => 'test']], ['$dateRange' => ['test' => 'test']]],
            [['$dateRange' => ['period' => 'test', 'start' => '2012-12-12']], ['$dateRange' => ['period' => 'test', 'start' => '2012-12-12']]],
            [['$dateRange' => ['period' => [], 'start' => '2012-12-12']], ['$dateRange' => ['period' => [], 'start' => '2012-12-12']]],
            [['$dateRange' => ['period' => ['test'], 'start' => '2012-12-12']], ['$dateRange' => ['period' => ['test'], 'start' => '2012-12-12']]],
            [['$dateRange' => ['period' => ['days', 30], 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => ['days', 30], 'start' => $now->subDays(30)->format('Y-m-d'), 'end' => $now->format('Y-m-d')]]],
            [['$dateRange' => ['period' => 'today', 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => 'today', 'start' => $now->format('Y-m-d'), 'end' => $now->format('Y-m-d')]]],
            [['$dateRange' => ['period' => 'yesterday', 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => 'yesterday', 'start' => $now->subDay()->format('Y-m-d'), 'end' => $now->subDay()->format('Y-m-d')]]],
            [['$dateRange' => ['period' => 'this_month', 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => 'this_month', 'start' => $now->firstOfMonth()->format('Y-m-d'), 'end' => $now->lastOfMonth()->format('Y-m-d')]]],
            [['$dateRange' => ['period' => 'last_month', 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => 'last_month', 'start' => $now->subMonth()->firstOfMonth()->format('Y-m-d'), 'end' => $now->subMonth()->lastOfMonth()->format('Y-m-d')]]],
            [['$dateRange' => ['period' => 'this_quarter', 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => 'this_quarter', 'start' => $now->firstOfQuarter()->format('Y-m-d'), 'end' => $now->lastOfQuarter()->format('Y-m-d')]]],
            [['$dateRange' => ['period' => 'last_quarter', 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => 'last_quarter', 'start' => $now->subQuarter()->firstOfQuarter()->format('Y-m-d'), 'end' => $now->subQuarter()->lastOfQuarter()->format('Y-m-d')]]],
            [['$dateRange' => ['period' => 'this_year', 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => 'this_year', 'start' => $now->firstOfYear()->format('Y-m-d'), 'end' => $now->lastOfYear()->format('Y-m-d')]]],
            [['$dateRange' => ['period' => 'last_year', 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => 'last_year', 'start' => $now->subYear()->firstOfYear()->format('Y-m-d'), 'end' => $now->subYear()->lastOfYear()->format('Y-m-d')]]],
            [['$dateRange' => ['period' => 'all_time', 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => 'all_time', 'start' => '2012-12-12', 'end' => $now->format('Y-m-d')]]],
            [['$dateRange' => ['period' => 'next_90_days', 'start' => '2012-12-12', 'end' => '2012-12-12']], ['$dateRange' => ['period' => 'next_90_days', 'start' => $now->addDay()->format('Y-m-d'), 'end' => $now->addDays(91)->format('Y-m-d')]]],
        ];

        foreach ($parametersToTest as $tuple) {
            $report->parameters = $tuple[0];
            $this->assertEquals($report->getParameters(), $tuple[1], (string) json_encode($tuple));
        }
    }
}
