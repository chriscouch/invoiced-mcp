<?php

namespace App\Tests\Integrations\Adyen\ReportHandler;

use App\Integrations\Adyen\Interfaces\ReportHandlerInterface;
use App\Integrations\Adyen\Reconciliation\AdyenReportExtractor;
use App\Integrations\Adyen\Reconciliation\AdyenReportStorage;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\Tests\AppTestCase;
use Generator;
use mikehaertl\tmp\File;
use Mockery;

abstract class AbstractReportHandlerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    abstract protected function getHandler(): ReportHandlerInterface;

    protected function createMerchantAccount(string $reference): MerchantAccount
    {
        return self::getTestDataFactory()->createMerchantAccount(AdyenGateway::ID, $reference, []);
    }

    protected function extractFile(string $filename): Generator
    {
        $csv = (string) file_get_contents(dirname(__DIR__).'/data/'.$filename);
        $tmpFile = new File($csv);
        $reportStorage = Mockery::mock(AdyenReportStorage::class);
        $reportStorage->shouldReceive('retrieve')
            ->andReturn($tmpFile);
        $extractor = new AdyenReportExtractor($reportStorage);

        return $extractor->extract($filename);
    }

    protected function handleFile(string $filename): void
    {
        $handler = $this->getHandler();
        $rows = $this->extractFile($filename);
        foreach ($rows as $row) {
            $handler->handleRow($row);
        }
        $handler->finish();
    }
}
