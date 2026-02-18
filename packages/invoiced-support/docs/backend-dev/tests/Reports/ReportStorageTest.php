<?php

namespace App\Tests\Reports;

use App\Core\Files\Libs\S3FileCreator;

use App\Reports\Interfaces\PresetReportInterface;
use App\Reports\Libs\ReportStorage;
use App\Reports\Models\Report as ReportModel;
use App\Reports\Output\Csv;
use App\Reports\Output\Html;
use App\Reports\Output\Json;
use App\Reports\Output\Pdf;
use App\Tests\AppTestCase;
use Mockery;

class ReportStorageTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    private function getStorage(): ReportStorage
    {
        $json = Mockery::mock(Json::class);
        $json->shouldReceive('generate')->andReturn(['test' => 'data']);
        return new ReportStorage(
            Mockery::mock(Csv::class),
            Mockery::mock(Html::class),
            $json,
            Mockery::mock(Pdf::class),
            Mockery::mock(S3FileCreator::class),
            'us-test-reqion',
            'test',
            'test-bucket',
        );
    }

    public function testPersist(): void
    {
        $storage = $this->getStorage();

        /** @var PresetReportInterface $presetReport */
        $presetReport = self::getService('test.preset_report_factory')->get('a_r_overview');
        $report = $presetReport->generate(self::$company, []);
        $saved = $storage->persist($report, 'a_r_overview');

        $this->assertInstanceOf(ReportModel::class, $saved);
        $this->assertEquals('A/R Overview', $saved->title);
        $this->assertGreaterThan(0, $saved->timestamp);
        $this->assertEquals('a_r_overview', $saved->type);
        $this->assertNotEmpty($saved->data);
    }
}
