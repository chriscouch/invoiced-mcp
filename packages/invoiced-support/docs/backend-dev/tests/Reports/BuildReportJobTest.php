<?php

namespace App\Tests\Reports;

use App\Companies\Models\Member;
use App\EntryPoint\QueueJob\BuildReportJob;
use App\Reports\Exceptions\ReportException;
use App\Reports\Libs\StartReportJob;
use App\Reports\Models\Report;
use App\Tests\AppTestCase;

class BuildReportJobTest extends AppTestCase
{
    private static Report $preset;
    private static Report $custom;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getStarter(): StartReportJob
    {
        return self::getService('test.start_report_job');
    }

    private function getJob(): BuildReportJob
    {
        return self::getService('test.build_report_job');
    }

    public function testStartInvalidDefinition(): void
    {
        $this->expectException(ReportException::class);

        $this->getStarter()->start(self::$company, new Member(), 'custom', '{"invalid": true}', []);
    }

    public function testStartPreset(): void
    {
        $report = $this->getStarter()->start(self::$company, null, 'aging_summary', null, []);
        $this->assertEquals([
            'created_at' => $report->created_at,
            'updated_at' => $report->updated_at,
            'csv_url' => null,
            'data' => [],
            'definition' => null,
            'filename' => '',
            'id' => $report->id,
            'parameters' => [],
            'pdf_url' => null,
            'timestamp' => $report->timestamp,
            'title' => '',
            'type' => 'aging_summary',
        ], $report->toArray());
        self::$preset = $report;
    }

    /**
     * @depends testStartPreset
     */
    public function testBuildPreset(): void
    {
        $this->getJob()->build(new Member(), self::$preset, false);
        $this->assertEquals('A/R Aging Summary', self::$preset->title);
        $this->assertNotEquals('', self::$preset->filename);
        $this->assertNotEquals([], self::$preset->data);
    }

    public function testStartCustom(): void
    {
        $definition = '{"version":1,"title":"My Report","sections":[{"title":"Section 1","object":"invoice","type":"chart","chart_type":"bar","multi_entity":false,"fields":[{"field":{"id":"customer.name"}},{"field":{"function":"sum","arguments":[{"id":"balance"}]}}],"filter":[{"operator":">","value":"0","field":{"id":"balance"}}],"group":[{"field":{"id":"customer.name"},"name":null,"expanded":false}],"sort":[]}]}';
        $report = $this->getStarter()->start(self::$company, new Member(), 'custom', $definition, ['$currency' => 'usd']);
        $this->assertEquals([
            'created_at' => $report->created_at,
            'updated_at' => $report->updated_at,
            'csv_url' => null,
            'data' => [],
            'definition' => $definition,
            'filename' => '',
            'id' => $report->id,
            'parameters' => ['$currency' => 'usd'],
            'pdf_url' => null,
            'timestamp' => $report->timestamp,
            'title' => '',
            'type' => 'custom',
        ], $report->toArray());
        self::$custom = $report;
    }

    /**
     * @depends testStartCustom
     */
    public function testBuildCustom(): void
    {
        $this->getJob()->build(new Member(), self::$custom, false);
        $this->assertEquals('My Report', self::$custom->title);
        $this->assertNotEquals('', self::$custom->filename);
        $this->assertNotEquals([], self::$custom->data);
    }

    public function testCustomReportWithoutDefinition(): void
    {
        try {
            $this->getStarter()->start(self::$company, new Member(), 'custom', null, []);
            $this->fail('Expected exception not thrown.');
        } catch (ReportException $e) {
            $this->assertEquals('A report definition is required for custom reports.', $e->getMessage());
        }
    }
}
