<?php

namespace App\Tests\Integrations\Intacct\Readers;

use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Extractors\IntacctCustomerExtractor;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Readers\IntacctCustomerReader;
use App\Integrations\Intacct\Transformers\IntacctCustomerTransformer;
use Carbon\CarbonImmutable;
use Intacct\Xml\Response\Result;
use Mockery;

class IntacctCustomerReaderTest extends AbstractIntacctReaderTest
{
    public function testGetId(): void
    {
        $api = $this->getApi();
        $transformer = $this->getTransformer(IntacctCustomerTransformer::class);
        $reader = $this->getReader($api, $transformer, IntacctCustomerReader::class);
        $this->assertEquals('intacct_customer', $reader->getId());
    }

    public function testIsEnabled(): void
    {
        $api = $this->getApi();
        $transformer = $this->getTransformer(IntacctCustomerTransformer::class);
        $reader = $this->getReader($api, $transformer, IntacctCustomerReader::class);
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_customers = false;
        $this->assertFalse($reader->isEnabled($syncProfile));
        $syncProfile->read_customers = true;
        $this->assertTrue($reader->isEnabled($syncProfile));
    }

    public function testInvoicesEmpty(): void
    {
        $api = $this->getApi();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_customers = true;
        $syncProfile->created_at = (new CarbonImmutable('2022-08-02'))->getTimestamp();
        $query = new ReadQuery(new CarbonImmutable('2022-08-02'));
        $transformer = $this->getTransformer(IntacctCustomerTransformer::class);
        $result = Mockery::mock(Result::class);
        $result->shouldReceive('getData')->once()->andReturn([]);
        $result->shouldReceive('getNumRemaining')->once()->andReturn(0);
        $result->shouldReceive('getTotalCount')->once()->andReturn(0);
        $result->shouldNotReceive('getResultId');
        $api->shouldReceive('getCustomers')->once()->andReturn($result);
        $api->shouldNotReceive('getMore');
        $reader = $this->getReader($api, $transformer, IntacctCustomerReader::class);
        $reader->syncAll(self::$account, $syncProfile, $query);
    }

    public function testSyncAll(): void
    {
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_customers = true;
        $syncProfile->created_at = (new CarbonImmutable('2022-08-02'))->getTimestamp();
        $query = new ReadQuery(new CarbonImmutable('2022-08-02'));
        $api = $this->getApi();
        $result = Mockery::mock(Result::class);
        $result->shouldNotReceive('getResultId')->once();
        $transformer = $this->getTransformer(IntacctCustomerTransformer::class);
        $transformedCustomer1 = new AccountingCustomer(IntegrationType::Intacct, '1');
        $transformer->shouldReceive('transform')->times(4)->andReturn($transformedCustomer1); /* @phpstan-ignore-line */
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')->withArgs([$transformedCustomer1])->times(4)->andReturn(new ImportRecordResult());
        $result->shouldReceive('getNumRemaining')->once()->andReturn(2);
        $result->shouldReceive('getNumRemaining')->once()->andReturn(0);
        $result->shouldReceive('getTotalCount')->once()->andReturn(0);
        $api->shouldReceive('getCustomers')->once()->andReturn($result);
        $api->shouldReceive('getMore')->once()->andReturn($result);
        $result->shouldReceive('getData')->twice()->andReturn([
            new \SimpleXMLElement('<test>
                    <RECORDNO>test1</RECORDNO>
                </test>'),
            new \SimpleXMLElement('<test>
                    <RECORDNO>test2</RECORDNO>
                </test>'),
        ]);
        $reader = $this->getReader($api, $transformer, IntacctCustomerReader::class, $loader);
        $reader->syncAll(self::$account, $syncProfile, $query);
    }

    protected function getExtractor(IntacctApi $api): ExtractorInterface
    {
        return new IntacctCustomerExtractor($api);
    }
}
