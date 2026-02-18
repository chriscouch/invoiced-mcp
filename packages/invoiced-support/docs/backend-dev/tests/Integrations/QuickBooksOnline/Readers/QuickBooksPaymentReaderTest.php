<?php

namespace App\Tests\Integrations\QuickBooksOnline\Readers;

use App\AccountsReceivable\Models\Invoice;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\RetryContextFactory;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksExtractorFactory;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksPaymentExtractor;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Readers\QuickBooksPaymentReader;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksPaymentTransformer;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksTransformerFactory;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class QuickBooksPaymentReaderTest extends AppTestCase
{
    private static string $jsonDIR;
    private static QuickBooksOnlineSyncProfile $syncProfile;
    private static array $testPaymentQueries;
    private static \stdClass $testCustomer;
    private static \stdClass $testInvoice;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::$customer->name = 'Test';
        self::$customer->saveOrFail();
        self::hasQuickBooksAccount();
        self::$syncProfile = new QuickBooksOnlineSyncProfile();
        self::$syncProfile->read_cursor = (new CarbonImmutable('2021-11-14'))->getTimestamp();
        self::$syncProfile->read_payments = true;
        self::$syncProfile->read_invoices = true;
        self::$syncProfile->read_credit_notes = true;
        self::$syncProfile->read_pdfs = false;
        self::$syncProfile->read_invoices_as_drafts = false;
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->save();

        self::$jsonDIR = dirname(__DIR__).'/json/quickbooks_payment_importer';
        self::$testPaymentQueries = [
            json_decode((string) file_get_contents(self::$jsonDIR.'/qbo_payment_query_1.json'))->QueryResponse->Payment,
            json_decode((string) file_get_contents(self::$jsonDIR.'/qbo_payment_query_2.json'))->QueryResponse->Payment,
        ];

        self::$testCustomer = json_decode((string) file_get_contents(dirname(__DIR__).'/json/quickbooks_invoice_importer/quickbooks_invoice_importer_customer_1.json'))->Customer;
        self::$testInvoice = json_decode((string) file_get_contents(dirname(__DIR__).'/json/quickbooks_invoice_importer/quickbooks_invoice_importer_result_1.json'))->QueryResponse->Invoice[0];
    }

    private function getReader(QuickBooksApi $api, QuickBooksPaymentTransformer $transformer, ?LoaderInterface $loader = null): QuickBooksPaymentReader
    {
        $loaderFactory = $loader ? $this->getLoader($loader) : self::getService('test.accounting_loader_factory');
        $extractorFactory = Mockery::mock(QuickBooksExtractorFactory::class);
        $extractorFactory->shouldReceive('get')->andReturn(new QuickBooksPaymentExtractor($api));
        $transformerFactory = Mockery::mock(QuickBooksTransformerFactory::class);
        $transformerFactory->shouldReceive('get')->andReturn($transformer);

        return new QuickBooksPaymentReader(self::getService('test.transaction_manager'), $extractorFactory, $transformerFactory, $loaderFactory);
    }

    private function getLoader(LoaderInterface $loader): AccountingLoaderFactory
    {
        $loaderFactory = Mockery::mock(AccountingLoaderFactory::class);
        $loaderFactory->shouldReceive('get')
            ->andReturn($loader);

        return $loaderFactory;
    }

    private function getTransformer(QuickBooksApi $quickBooksApi): QuickBooksPaymentTransformer
    {
        return new QuickBooksPaymentTransformer($quickBooksApi);
    }

    public function testGetId(): void
    {
        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $transformer = $this->getTransformer($quickBooksApi);
        $reader = $this->getReader($quickBooksApi, $transformer);
        $this->assertEquals('quickbooks_online_payment', $reader->getId());
    }

    public function testSuccessfulSync(): void
    {
        $invoice = new Invoice();
        $invoice->number = 'INV-1012';
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        /** QuickBooksInvoiceReader API calls */
        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $quickBooksApi->shouldReceive('setAccount');
        $quickBooksApi->shouldReceive([
            'getCustomer' => self::$testCustomer,
        ]);

        /* QuickBooksPaymentReader API calls */
        $quickBooksApi->shouldReceive('get')->withArgs([QuickBooksApi::CUSTOMER, '1'])
            ->andReturn(self::$testCustomer);
        $quickBooksApi->shouldReceive('get')->withArgs([QuickBooksApi::INVOICE, '456'])
            ->andReturn(self::$testInvoice);
        $quickBooksApi->shouldReceive(['query' => self::$testPaymentQueries[0]])
            ->withArgs([QuickBooksApi::PAYMENT, 1, "MetaData.LastUpdatedTime > '2021-11-14T00:00:00+00:00' AND TxnDate >= '2015-03-19'"])
            ->once();
        $quickBooksApi->shouldReceive(['query' => []])
            ->withArgs([QuickBooksApi::PAYMENT, 2, "MetaData.LastUpdatedTime > '2021-11-14T00:00:00+00:00' AND TxnDate >= '2015-03-19'"])
            ->once();

        // Include reconciliation error (to test the deletion after success)
        $error = new ReconciliationError();
        $error->object = ObjectType::Invoice->typeName();
        $error->accounting_id = self::$testPaymentQueries[0][0]->Id;
        $error->integration_id = IntegrationType::QuickBooksOnline->value;
        $error->retry_context = (object) [
            'object' => 'payment',
            'accountingId' => self::$testPaymentQueries[0][0]->Id,
        ];
        $error->saveOrFail();

        $transformer = $this->getTransformer($quickBooksApi);

        $query = new ReadQuery(new CarbonImmutable('2021-11-14'), new CarbonImmutable('2015-03-19'));

        $reader = $this->getReader($quickBooksApi, $transformer);
        $reader->syncAll(self::$quickbooksAccount, self::$syncProfile, $query);

        // Test that error has been removed.
        $error = ReconciliationError::where('object', 'payment')
            ->where('accounting_id', self::$testPaymentQueries[0][0]->Id)
            ->oneOrNull();
        $this->assertNull($error);

        // Test that a mapping has been created for the invoice (done through reader)
        $mapping = AccountingPaymentMapping::where('accounting_id', self::$testPaymentQueries[0][0]->Id)
            ->where('integration_id', IntegrationType::QuickBooksOnline->value)
            ->oneOrNull();
        $this->assertNotNull($mapping);
        $mapping->delete(); // Delete mapping for future tests.
    }

    public function testLoadException(): void
    {
        $testExceptionMessage = 'SAMPLE: Invalid record';

        /** QuickBooksInvoiceReader API calls */
        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $quickBooksApi->shouldReceive('setAccount');
        $quickBooksApi->shouldReceive([
            'getCustomer' => self::$testCustomer,
        ]);

        /* QuickBooksPaymentReader API calls */
        $quickBooksApi->shouldReceive('get')->withArgs([QuickBooksApi::CUSTOMER, '1'])
            ->andReturn(self::$testCustomer);
        $quickBooksApi->shouldReceive('get')->withArgs([QuickBooksApi::INVOICE, '456'])
            ->andReturn(self::$testInvoice);
        $quickBooksApi->shouldReceive(['query' => self::$testPaymentQueries[0]])
            ->withArgs([QuickBooksApi::PAYMENT, 1, "MetaData.LastUpdatedTime > '2021-11-14T00:00:00+00:00' AND TxnDate >= '2015-03-19'"])
            ->once();
        $quickBooksApi->shouldReceive(['query' => []])
            ->withArgs([QuickBooksApi::PAYMENT, 2, "MetaData.LastUpdatedTime > '2021-11-14T00:00:00+00:00' AND TxnDate >= '2015-03-19'"])
            ->once();

        $transformer = Mockery::mock($this->getTransformer($quickBooksApi))->makePartial();
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')
            ->andThrow(new LoadException($testExceptionMessage));

        $query = new ReadQuery(new CarbonImmutable('2021-11-14'), new CarbonImmutable('2015-03-19'));

        $reader = $this->getReader($quickBooksApi, $transformer, $loader);
        $reader->syncAll(self::$quickbooksAccount, self::$syncProfile, $query);

        // Test that reconciliation error was created on import failure
        /** @var ReconciliationError $error */
        $error = ReconciliationError::where('accounting_id', self::$testPaymentQueries[0][0]->Id)
            ->where('integration_id', IntegrationType::QuickBooksOnline->value)
            ->oneOrNull();
        $this->assertNotNull($error);

        // Validate retry event
        $context = (new RetryContextFactory())->make($error);
        $this->assertEquals([
            'accountingId' => self::$testPaymentQueries[0][0]->Id,
            'object' => 'payment',
            'invoicedId' => null,
            'reader' => 'quickbooks_online_payment',
            'accounting_id' => self::$testPaymentQueries[0][0]->Id,
            'object_id' => null,
        ], $context?->data);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testStartDate(): void
    {
        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $quickBooksApi->shouldReceive('setAccount');
        $quickBooksApi->shouldReceive('query')
            ->withArgs([QuickBooksApi::PAYMENT, 1, "MetaData.LastUpdatedTime > '2021-11-14T00:00:00+00:00' AND TxnDate >= '2021-04-26'"])
            ->andReturn([]); // Only testing the query, so the results are not important.

        $transformer = $this->getTransformer($quickBooksApi);

        $query = new ReadQuery(new CarbonImmutable('2021-11-14'), new CarbonImmutable('2021-04-26'));

        $reader = $this->getReader($quickBooksApi, $transformer);
        $reader->syncAll(self::$quickbooksAccount, self::$syncProfile, $query);
    }
}
