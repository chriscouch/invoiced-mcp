<?php

namespace App\Tests\Integrations\Intacct\Readers;

use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Extractors\IntacctOrderEntryTransactionExtractor;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Readers\IntacctOrderEntryInvoiceReader;
use App\Integrations\Intacct\Transformers\IntacctOrderEntryInvoiceTransformer;
use Carbon\CarbonImmutable;
use Intacct\Xml\Response\Result;
use Mockery;

class IntacctOrderEntryInvoiceReaderTest extends AbstractIntacctReaderTest
{
    private const FIELDS = [
        'RECORDNO',
        'PRRECORDKEY',
        'DOCNO',
        'CURRENCY',
        'STATE',
        'WHENPOSTED',
        'WHENDUE',
        'TRX_TOTALPAID',
        'TERM.NAME',
        'MESSAGE',
        'PONUMBER',
        'CONTRACTID',
        'SODOCUMENTENTRIES',
        'SUBTOTALS',
        // customers
        'CUSTREC',
        'CUSTVENDID',
        'CUSTVENDNAME',
        // ship to
        'SHIPTO.PRINTAS',
        'SHIPTO.MAILADDRESS.ADDRESS1',
        'SHIPTO.MAILADDRESS.ADDRESS2',
        'SHIPTO.MAILADDRESS.CITY',
        'SHIPTO.MAILADDRESS.STATE',
        'SHIPTO.MAILADDRESS.ZIP',
        'SHIPTO.MAILADDRESS.COUNTRYCODE',
        'MEGAENTITYID',
    ];

    private const FIELDS_BILL_TO = [
        'RECORDNO',
        'PRRECORDKEY',
        'DOCNO',
        'CURRENCY',
        'STATE',
        'WHENPOSTED',
        'WHENDUE',
        'TRX_TOTALPAID',
        'TERM.NAME',
        'MESSAGE',
        'PONUMBER',
        'CONTRACTID',
        'SODOCUMENTENTRIES',
        'SUBTOTALS',
        // customers
        'CUSTREC',
        'CUSTVENDID',
        'CUSTVENDNAME',
        // ship to
        'SHIPTO.PRINTAS',
        'SHIPTO.MAILADDRESS.ADDRESS1',
        'SHIPTO.MAILADDRESS.ADDRESS2',
        'SHIPTO.MAILADDRESS.CITY',
        'SHIPTO.MAILADDRESS.STATE',
        'SHIPTO.MAILADDRESS.ZIP',
        'SHIPTO.MAILADDRESS.COUNTRYCODE',
        'MEGAENTITYID',
        // bill to
        'BILLTOKEY',
        'BILLTO.PRINTAS',
        'BILLTO.EMAIL1',
        'BILLTO.EMAIL2',
        'BILLTO.PHONE1',
        'BILLTO.MAILADDRESS.ADDRESS1',
        'BILLTO.MAILADDRESS.ADDRESS2',
        'BILLTO.MAILADDRESS.CITY',
        'BILLTO.MAILADDRESS.STATE',
        'BILLTO.MAILADDRESS.ZIP',
        'BILLTO.MAILADDRESS.COUNTRYCODE',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasInvoice();
    }

    public function testGetId(): void
    {
        $api = $this->getApi();
        $transformer = $this->getTransformer(IntacctOrderEntryInvoiceTransformer::class);
        $reader = $this->getReader($api, $transformer, IntacctOrderEntryInvoiceReader::class);
        $this->assertEquals('intacct_order_entry_invoice', $reader->getId());
    }

    public function testIsEnabled(): void
    {
        $api = $this->getApi();
        $transformer = $this->getTransformer(IntacctOrderEntryInvoiceTransformer::class);
        $reader = $this->getReader($api, $transformer, IntacctOrderEntryInvoiceReader::class);
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->invoice_types = ['test', 'test2'];
        $syncProfile->read_invoices = false;
        $this->assertFalse($reader->isEnabled($syncProfile));
        $syncProfile->read_invoices = true;
        $this->assertTrue($reader->isEnabled($syncProfile));
    }

    public function testInvoicesEmpty(): void
    {
        $api = $this->getApi();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_invoices = true;
        $syncProfile->invoice_types = ['test', 'test2'];
        $syncProfile->created_at = (new CarbonImmutable('2022-08-02'))->getTimestamp();
        $query = new ReadQuery(new CarbonImmutable('2022-08-02'));
        $transformer = $this->getTransformer(IntacctOrderEntryInvoiceTransformer::class);
        $result = Mockery::mock(Result::class);
        $result->shouldReceive('getData')->twice()->andReturn([]);
        $result->shouldReceive('getNumRemaining')->twice()->andReturn(0);
        $result->shouldReceive('getTotalCount')->twice()->andReturn(0);
        $result->shouldNotReceive('getResultId');
        $api->shouldReceive('getOrderEntryTransactions')->twice()->andReturn($result);
        $api->shouldNotReceive('getMore');
        $reader = $this->getReader($api, $transformer, IntacctOrderEntryInvoiceReader::class);
        $reader->syncAll(self::$account, $syncProfile, $query);
    }

    public function testSyncAll(): void
    {
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_invoices = true;
        $syncProfile->invoice_types = ['test'];
        $syncProfile->created_at = (new CarbonImmutable('2022-08-02'))->getTimestamp();
        $query = new ReadQuery(new CarbonImmutable('2022-08-02'));
        $api = $this->getApi();
        $result = Mockery::mock(Result::class);
        $result->shouldNotReceive('getResultId')->once();
        $transformer = $this->getTransformer(IntacctOrderEntryInvoiceTransformer::class);
        $transformedInvoice1 = new AccountingInvoice(IntegrationType::Intacct, '1', new AccountingCustomer(IntegrationType::Intacct, '1'));
        $transformer->shouldReceive('transform')->times(4)->andReturn($transformedInvoice1); /* @phpstan-ignore-line */
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')->withArgs([$transformedInvoice1])->times(4)->andReturn(new ImportRecordResult());
        $result->shouldReceive('getNumRemaining')->once()->andReturn(2);
        $result->shouldReceive('getNumRemaining')->once()->andReturn(0);
        $result->shouldReceive('getTotalCount')->once()->andReturn(0);
        $api->shouldReceive('getOrderEntryTransactions')->once()->andReturn($result);
        $api->shouldReceive('getMore')->once()->andReturn($result);
        $result->shouldReceive('getData')->twice()->andReturn([
            new \SimpleXMLElement('<test>
                    <RECORDNO>test1</RECORDNO>
                </test>'),
            new \SimpleXMLElement('<test>
                    <RECORDNO>test2</RECORDNO>
                </test>'),
        ]);
        $result2 = Mockery::mock(Result::class);
        $result2->shouldReceive('getData')->andReturn([
            new \SimpleXMLElement('<test>
                    <RECORDNO>test1</RECORDNO>
                    <DOCNO>INV1</DOCNO>
                </test>'),
            new \SimpleXMLElement('<test>
                    <RECORDNO>test2</RECORDNO>
                    <DOCNO>INV2</DOCNO>
                </test>'),
        ]);
        $result2->shouldReceive('getNumRemaining')->andReturn(0);
        $api->shouldReceive('getOrderEntryTransactionsByIds')
            ->withArgs(['test', ['test1', 'test2'], self::FIELDS])
            ->andReturn($result2);
        $api->shouldReceive('getOrderEntryPdf')
            ->withArgs(['test', 'INV1'])
            ->andReturn('pdf');
        $api->shouldReceive('getOrderEntryPdf')
            ->withArgs(['test', 'INV2'])
            ->andReturn('pdf2');
        $reader = $this->getReader($api, $transformer, IntacctOrderEntryInvoiceReader::class, $loader);
        $reader->syncAll(self::$account, $syncProfile, $query);
    }

    public function testSyncAllBillTo(): void
    {
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_invoices = true;
        $syncProfile->invoice_types = ['test'];
        $syncProfile->customer_import_type = IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_BILL_TO;
        $syncProfile->created_at = (new CarbonImmutable('2022-08-02'))->getTimestamp();
        $query = new ReadQuery(new CarbonImmutable('2022-08-02'));
        $api = $this->getApi();
        $result = Mockery::mock(Result::class);
        $result->shouldNotReceive('getResultId')->once();
        $transformer = $this->getTransformer(IntacctOrderEntryInvoiceTransformer::class);
        $transformedInvoice1 = new AccountingInvoice(IntegrationType::Intacct, '1', new AccountingCustomer(IntegrationType::Intacct, '1'));
        $transformer->shouldReceive('transform')->times(4)->andReturn($transformedInvoice1); /* @phpstan-ignore-line */
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')->withArgs([$transformedInvoice1])->times(4)->andReturn(new ImportRecordResult());
        $result->shouldReceive('getNumRemaining')->once()->andReturn(2);
        $result->shouldReceive('getNumRemaining')->once()->andReturn(0);
        $result->shouldReceive('getTotalCount')->once()->andReturn(0);
        $api->shouldReceive('getOrderEntryTransactions')->once()->andReturn($result);
        $api->shouldReceive('getMore')->once()->andReturn($result);
        $result->shouldReceive('getData')->twice()->andReturn([
            new \SimpleXMLElement('<test>
                    <RECORDNO>test1</RECORDNO>
                </test>'),
            new \SimpleXMLElement('<test>
                    <RECORDNO>test2</RECORDNO>
                </test>'),
        ]);
        $result2 = Mockery::mock(Result::class);
        $result2->shouldReceive('getData')->andReturn([
            new \SimpleXMLElement('<test>
                    <RECORDNO>test1</RECORDNO>
                    <DOCNO>INV1</DOCNO>
                </test>'),
            new \SimpleXMLElement('<test>
                    <RECORDNO>test2</RECORDNO>
                    <DOCNO>INV2</DOCNO>
                </test>'),
        ]);
        $result2->shouldReceive('getNumRemaining')->andReturn(0);
        $api->shouldReceive('getOrderEntryTransactionsByIds')
            ->withArgs(['test', ['test1', 'test2'], self::FIELDS_BILL_TO])
            ->andReturn($result2);
        $api->shouldReceive('getOrderEntryPdf')
            ->withArgs(['test', 'INV1'])
            ->andReturn('pdf');
        $api->shouldReceive('getOrderEntryPdf')
            ->withArgs(['test', 'INV2'])
            ->andReturn('pdf2');
        $reader = $this->getReader($api, $transformer, IntacctOrderEntryInvoiceReader::class, $loader);
        $reader->syncAll(self::$account, $syncProfile, $query);
    }

    public function getTransformer(string $class): TransformerInterface
    {
        $transformer = parent::getTransformer($class);
        $transformer->shouldReceive('setDocumentType'); /* @phpstan-ignore-line */

        return $transformer;
    }

    protected function getExtractor(IntacctApi $api): ExtractorInterface
    {
        return new IntacctOrderEntryTransactionExtractor($api);
    }
}
