<?php

namespace App\Tests\Integrations\Intacct\Readers;

use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Extractors\IntacctPaymentExtractor;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Readers\IntacctPaymentReader;
use App\Integrations\Intacct\Transformers\IntacctPaymentTransformer;
use Carbon\CarbonImmutable;
use Intacct\Xml\Response\Result;
use Mockery;
use SimpleXMLElement;

class IntacctPaymentReaderTest extends AbstractIntacctReaderTest
{
    private const FIELDS = [
        'RECORDNO',
        'CUSTOMERID',
        'CUSTOMERNAME',
        'STATE',
        'CURRENCY',
        'RECEIPTDATE',
        'DOCNUMBER',
        'RECORDID',
        'PAYMENTTYPE',
        'INVOICES',
        'CREDITS',
        'AUWHENCREATED',
        'MEGAENTITYID',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasInvoice();
        self::hasPayment();
    }

    public function testGetId(): void
    {
        $api = $this->getApi();
        $transformer = $this->getTransformer(IntacctPaymentTransformer::class);
        $reader = $this->getReader($api, $transformer, IntacctPaymentReader::class);
        $this->assertEquals('intacct_ar_payment', $reader->getId());
    }

    public function testIsEnabled(): void
    {
        $api = $this->getApi();
        $transformer = $this->getTransformer(IntacctPaymentTransformer::class);
        $reader = $this->getReader($api, $transformer, IntacctPaymentReader::class);
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_payments = false;
        $this->assertFalse($reader->isEnabled($syncProfile));
        $syncProfile->read_payments = true;
        $this->assertTrue($reader->isEnabled($syncProfile));
    }

    public function testInvoicesEmpty(): void
    {
        $api = $this->getApi();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_payments = true;
        $syncProfile->created_at = (new CarbonImmutable('2022-08-02'))->getTimestamp();
        $query = new ReadQuery(new CarbonImmutable('2022-08-02'));
        $transformer = $this->getTransformer(IntacctPaymentTransformer::class);
        $result = Mockery::mock(Result::class);
        $result->shouldReceive('getData')->once()->andReturn([]);
        $result->shouldReceive('getNumRemaining')->once()->andReturn(0);
        $result->shouldReceive('getTotalCount')->once()->andReturn(0);
        $result->shouldNotReceive('getResultId');
        $api->shouldReceive('getPayments')->once()->andReturn($result);
        $api->shouldNotReceive('getMore');
        $reader = $this->getReader($api, $transformer, IntacctPaymentReader::class);
        $reader->syncAll(self::$account, $syncProfile, $query);
    }

    public function testSyncAll(): void
    {
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_payments = true;
        $syncProfile->created_at = (new CarbonImmutable('2022-08-02'))->getTimestamp();
        $query = new ReadQuery(new CarbonImmutable('2022-08-02'));
        $api = $this->getApi();
        $result = Mockery::mock(Result::class);
        $result->shouldNotReceive('getResultId')->once();
        $transformer = $this->getTransformer(IntacctPaymentTransformer::class);
        $transformedPayment1 = new AccountingPayment(IntegrationType::Intacct, '1');
        $transformer->shouldReceive('transform')->times(4)->andReturn($transformedPayment1); /* @phpstan-ignore-line */
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')->withArgs([$transformedPayment1])->times(4)->andReturn(new ImportRecordResult());
        $result->shouldReceive('getNumRemaining')->once()->andReturn(2);
        $result->shouldReceive('getNumRemaining')->once()->andReturn(0);
        $result->shouldReceive('getTotalCount')->once()->andReturn(0);
        $api->shouldReceive('getPayments')->once()->andReturn($result);
        $api->shouldReceive('getMore')->once()->andReturn($result);
        $result->shouldReceive('getData')->twice()->andReturn([
            new SimpleXMLElement('<test>
                    <RECORDNO>test1</RECORDNO>
                </test>'),
            new SimpleXMLElement('<test>
                    <RECORDNO>test2</RECORDNO>
                </test>'),
        ]);
        $result2 = Mockery::mock(Result::class);
        $result2->shouldReceive('getData')->andReturn([
            new SimpleXMLElement('<test>
                    <RECORDNO>test1</RECORDNO>
                </test>'),
            new SimpleXMLElement('<test>
                    <RECORDNO>test2</RECORDNO>
                </test>'),
        ]);
        $api->shouldReceive('getPaymentsByIds')
            ->withArgs([['test1', 'test2'], self::FIELDS])
            ->twice()
            ->andReturn($result2);
        $reader = $this->getReader($api, $transformer, IntacctPaymentReader::class, $loader);
        $reader->syncAll(self::$account, $syncProfile, $query);
    }

    protected function getExtractor(IntacctApi $api): ExtractorInterface
    {
        return new IntacctPaymentExtractor($api);
    }
}
