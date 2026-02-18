<?php

namespace App\Tests\Integrations\Intacct\Extractors;

use App\Integrations\AccountingSync\Enums\TransformFieldType;
use App\Integrations\AccountingSync\Models\AccountingSyncFieldMapping;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Extractors\IntacctOrderEntryTransactionExtractor;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Metadata\Models\CustomField;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Intacct\Xml\Response\Result;
use Mockery;
use SimpleXMLElement;

class IntacctOrderEntryTransactionExtractorTest extends AppTestCase
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
        // bill to
        'MEGAENTITYID',
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

    private static string $xmlDIR;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();

        self::$xmlDIR = dirname(__DIR__).'/xml/intacct_order_entry_invoice_importer';

        $customField = new CustomField();
        $customField->object = 'customer';
        $customField->id = 'opstech';
        $customField->name = 'Opstech';
        $customField->type = CustomField::FIELD_TYPE_BOOLEAN;
        $customField->saveOrFail();

        $mapping = new AccountingSyncFieldMapping();
        $mapping->integration = IntegrationType::Intacct;
        $mapping->object_type = 'customer';
        $mapping->source_field = 'OPSTECH';
        $mapping->destination_field = 'metadata/opstech';
        $mapping->data_type = TransformFieldType::String;
        $mapping->enabled = true;
        $mapping->save();
    }

    protected function tearDown(): void
    {
        if (self::$company->features->has('multi_currency')) {
            self::$company->features->disable('multi_currency');
        }
    }

    private function getExtractor(IntacctApi $api): IntacctOrderEntryTransactionExtractor
    {
        return new IntacctOrderEntryTransactionExtractor($api);
    }

    public function testGetObject(): void
    {
        $account = new IntacctAccount();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_pdfs = true;

        $api = Mockery::mock(IntacctApi::class);
        $api->shouldReceive('setAccount')->once();

        // Transaction mock
        $invoiceXml = new SimpleXMLElement('<sodocument>
                <RECORDNO>test1</RECORDNO>
                <DOCNO>INV1</DOCNO>
            </sodocument>');
        $api->shouldReceive('getOrderEntryTransaction')
            ->withArgs(['Sales Invoice', '1234', self::FIELDS])
            ->once()
            ->andReturn($invoiceXml);

        // PDF mock
        $api->shouldReceive('getOrderEntryPdf')
            ->once()
            ->withArgs(['Sales Invoice', 'INV1'])
            ->andReturn('pdf');

        $extractor = $this->getExtractor($api);
        $extractor->setDocumentType('Sales Invoice');
        $extractor->initialize($account, $syncProfile);

        $transaction = $extractor->getObject('1234');

        $this->assertEquals($invoiceXml, $transaction->document);
        $this->assertEquals('pdf', $transaction->pdf);
    }

    public function testGetObjectBillTo(): void
    {
        $account = new IntacctAccount();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_pdfs = true;
        $syncProfile->customer_import_type = IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_BILL_TO;

        $api = Mockery::mock(IntacctApi::class);
        $api->shouldReceive('setAccount')->once();

        // Transaction mock
        $invoiceXml = new SimpleXMLElement('<sodocument>
                <RECORDNO>test1</RECORDNO>
                <DOCNO>INV1</DOCNO>
            </sodocument>');
        $api->shouldReceive('getOrderEntryTransaction')
            ->withArgs(['Sales Invoice', '1234', self::FIELDS_BILL_TO])
            ->once()
            ->andReturn($invoiceXml);

        // PDF mock
        $api->shouldReceive('getOrderEntryPdf')
            ->once()
            ->withArgs(['Sales Invoice', 'INV1'])
            ->andReturn('pdf');

        $extractor = $this->getExtractor($api);
        $extractor->setDocumentType('Sales Invoice');
        $extractor->initialize($account, $syncProfile);

        $transaction = $extractor->getObject('1234');

        $this->assertEquals($invoiceXml, $transaction->document);
        $this->assertEquals('pdf', $transaction->pdf);
    }

    public function testGetObjects(): void
    {
        $account = new IntacctAccount();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_pdfs = false;
        $syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();

        $invoices1 = (string) file_get_contents(self::$xmlDIR.'/standard/intacct_invoices_1.xml');

        $invoices2 = (string) file_get_contents(self::$xmlDIR.'/standard/intacct_invoices_2.xml');
        $invoices2 = str_replace('self::$customer->number', self::$customer->number, $invoices2);
        $invoices2 = str_replace('self::$customer->name', self::$customer->name, $invoices2);

        $api = Mockery::mock(IntacctApi::class);
        $api->shouldReceive('getLogger')
            ->andReturn(self::$logger);
        $api->shouldReceive('setAccount');
        $api->shouldReceive('getOrderEntryTransactions')
            ->withArgs(['Sales Invoice', ['RECORDNO'],  'TRX_TOTALDUE > 0 AND TRX_TOTALENTERED > 0'])
            ->andReturn(new Result(simplexml_load_string($invoices1)->{'operation'}->{'result'})); /* @phpstan-ignore-line */

        $api->shouldReceive('getOrderEntryTransactionsByIds')
            ->withArgs(['Sales Invoice', ['456', '165', '109', '120', '150'], self::FIELDS])
            ->andReturn(new Result(simplexml_load_string($invoices2)->{'operation'}->{'result'})); /* @phpstan-ignore-line */

        $extractor = $this->getExtractor($api);
        $extractor->setDocumentType('Sales Invoice');
        $query = new ReadQuery(openItemsOnly: true);
        $extractor->initialize($account, $syncProfile);

        $generator = $extractor->getObjects($syncProfile, $query);

        $results = iterator_to_array($generator);

        $this->assertCount(5, $results);
    }

    public function testGetObjectsBillTo(): void
    {
        $account = new IntacctAccount();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_pdfs = false;
        $syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        $syncProfile->customer_import_type = IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_BILL_TO;

        $invoices1 = (string) file_get_contents(self::$xmlDIR.'/standard/intacct_invoices_1.xml');

        $invoices2 = (string) file_get_contents(self::$xmlDIR.'/standard/intacct_invoices_bill_to.xml');
        $invoices2 = str_replace('self::$customer->number', self::$customer->number, $invoices2);
        $invoices2 = str_replace('self::$customer->name', self::$customer->name, $invoices2);

        $api = Mockery::mock(IntacctApi::class);
        $api->shouldReceive('getLogger')
            ->andReturn(self::$logger);
        $api->shouldReceive('setAccount');
        $api->shouldReceive('getOrderEntryTransactions')
            ->withArgs(['Sales Invoice', ['RECORDNO'],  'TRX_TOTALDUE > 0 AND TRX_TOTALENTERED > 0'])
            ->andReturn(new Result(simplexml_load_string($invoices1)->{'operation'}->{'result'})); /* @phpstan-ignore-line */

        $api->shouldReceive('getOrderEntryTransactionsByIds')
            ->withArgs(fn ($a, $b, $c) => 'Sales Invoice' === $a
                && $b === ['456', '165', '109', '120', '150']
                && (self::FIELDS_BILL_TO === $c || $c === array_merge(self::FIELDS, ['BILLTO.EMAIL1'])))
            ->andReturn(new Result(simplexml_load_string($invoices2)->{'operation'}->{'result'})); /* @phpstan-ignore-line */

        $extractor = $this->getExtractor($api);
        $extractor->setDocumentType('Sales Invoice');
        $query = new ReadQuery(openItemsOnly: true);
        $extractor->initialize($account, $syncProfile);

        $generator = $extractor->getObjects($syncProfile, $query);

        $results = iterator_to_array($generator);

        $this->assertCount(5, $results);
    }

    public function testGetObjectsPaymentPlan(): void
    {
        $account = new IntacctAccount();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_pdfs = false;
        $syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        $syncProfile->customer_import_type = IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_CUSTOMER;
        $syncProfile->payment_plan_import_settings = (object) ['document_type' => 'Sales Invoice', 'mapping' => ['REVRECSTARTDATE' => 'date']];

        $invoices1 = (string) file_get_contents(self::$xmlDIR.'/payment_plan/intacct_invoices_1.xml');

        $invoices2 = (string) file_get_contents(self::$xmlDIR.'/payment_plan/intacct_invoices_2.xml');
        $invoices2 = str_replace('self::$customer->number', self::$customer->number, $invoices2);
        $invoices2 = str_replace('self::$customer->name', self::$customer->name, $invoices2);

        $api = Mockery::mock(IntacctApi::class);
        $api->shouldReceive('getLogger')
            ->andReturn(self::$logger);
        $api->shouldReceive('setAccount');
        $api->shouldReceive('getOrderEntryTransactions')
            ->withArgs(['Sales Invoice', ['RECORDNO'], 'TRX_TOTALDUE > 0 AND TRX_TOTALENTERED > 0'])
            ->andReturn(new Result(simplexml_load_string($invoices1)->{'operation'}->{'result'})); /* @phpstan-ignore-line */

        $api->shouldReceive('getOrderEntryTransactionsByIds')
            ->withArgs(['Sales Invoice', ['1456', '1165', '1109', '1120', '1150'], self::FIELDS])
            ->andReturn(new Result(simplexml_load_string($invoices2)->{'operation'}->{'result'})); /* @phpstan-ignore-line */

        $extractor = $this->getExtractor($api);
        $extractor->setDocumentType('Sales Invoice');
        $query = new ReadQuery(openItemsOnly: true);
        $extractor->initialize($account, $syncProfile);

        $generator = $extractor->getObjects($syncProfile, $query);

        $results = iterator_to_array($generator);

        $this->assertCount(5, $results);
    }

    public function testGetObjectsInvalidPaymentPlan(): void
    {
        $account = new IntacctAccount();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_pdfs = false;
        $syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();

        $invoices1 = (string) file_get_contents(self::$xmlDIR.'/payment_plan_invalid/intacct_invoices_1.xml');

        $invoices2 = (string) file_get_contents(self::$xmlDIR.'/payment_plan_invalid/intacct_invoices_2.xml');
        $invoices2 = str_replace('self::$customer->number', self::$customer->number, $invoices2);
        $invoices2 = str_replace('self::$customer->name', self::$customer->name, $invoices2);

        $api = Mockery::mock(IntacctApi::class);
        $api->shouldReceive('getLogger')
            ->andReturn(self::$logger);
        $api->shouldReceive('setAccount');
        $api->shouldReceive('getOrderEntryTransactions')
            ->withArgs(['Sales Invoice', ['RECORDNO'], 'TRX_TOTALDUE > 0 AND TRX_TOTALENTERED > 0'])
            ->andReturn(new Result(simplexml_load_string($invoices1)->{'operation'}->{'result'})); /* @phpstan-ignore-line */

        $api->shouldReceive('getOrderEntryTransactionsByIds')
            ->withArgs(['Sales Invoice', ['2456'], self::FIELDS])
            ->andReturn(new Result(simplexml_load_string($invoices2)->{'operation'}->{'result'})); /* @phpstan-ignore-line */

        $extractor = $this->getExtractor($api);
        $extractor->setDocumentType('Sales Invoice');
        $query = new ReadQuery(openItemsOnly: true);
        $extractor->initialize($account, $syncProfile);

        $generator = $extractor->getObjects($syncProfile, $query);

        $results = iterator_to_array($generator);

        $this->assertCount(1, $results);
    }
}
