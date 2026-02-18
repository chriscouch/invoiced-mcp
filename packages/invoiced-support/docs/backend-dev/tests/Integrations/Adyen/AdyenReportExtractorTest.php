<?php

namespace App\Tests\Integrations\Adyen;

use App\Integrations\Adyen\Reconciliation\AdyenReportExtractor;
use App\Integrations\Adyen\Reconciliation\AdyenReportStorage;
use App\Tests\AppTestCase;
use mikehaertl\tmp\File;
use Mockery;

class AdyenReportExtractorTest extends AppTestCase
{
    public function testExtract(): void
    {
        $csv = (string) file_get_contents(__DIR__.'/data/balanceplatform_payout_report_2025_03_27.csv');
        $tmpFile = new File($csv);
        $reportStorage = Mockery::mock(AdyenReportStorage::class);
        $reportStorage->shouldReceive('retrieve')
            ->withArgs(['balanceplatform_payout_report_2025_03_27.csv'])
            ->andReturn($tmpFile);
        $extractor = new AdyenReportExtractor($reportStorage);
        $generator = $extractor->extract('balanceplatform_payout_report_2025_03_27.csv');
        $result = iterator_to_array($generator);
        $expected = json_decode((string) file_get_contents(__DIR__.'/data/extractedReport.json'), true);
        $this->assertEquals($expected, $result);
    }
}
