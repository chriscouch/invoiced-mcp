<?php

namespace App\Tests\Integrations\QuickBooksOnline\Readers;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\RetryContextFactory;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksCreditMemoExtractor;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksExtractorFactory;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Readers\QuickBooksCreditMemoReader;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksCreditMemoTransformer;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksTransformerFactory;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class QuickBooksCreditMemoReaderTest extends AppTestCase
{
    private static string $jsonDIR;
    private static QuickBooksOnlineSyncProfile $syncProfile;
    private static array $testCreditNoteQueries;
    private static \stdClass $testCustomer;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasQuickBooksAccount();
        self::$syncProfile = new QuickBooksOnlineSyncProfile();
        self::$syncProfile->read_cursor = (new CarbonImmutable('2021-11-14'))->getTimestamp();
        self::$syncProfile->read_credit_notes = true;
        self::$syncProfile->read_pdfs = false;
        self::$syncProfile->read_invoices_as_drafts = false;
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->save();

        self::$jsonDIR = dirname(__DIR__).'/json/quickbooks_credit_memo_importer';
        self::$testCreditNoteQueries = [
            json_decode((string) file_get_contents(self::$jsonDIR.'/qbo_credit_memo_query_1.json'))->QueryResponse->CreditMemo,
            json_decode((string) file_get_contents(self::$jsonDIR.'/qbo_credit_memo_query_2.json'))->QueryResponse->CreditMemo,
            json_decode((string) file_get_contents(self::$jsonDIR.'/qbo_credit_memo_query_3.json'))->QueryResponse->CreditMemo,
        ];
        self::$testCustomer = json_decode((string) file_get_contents(dirname(__DIR__).'/json/quickbooks_invoice_importer/quickbooks_invoice_importer_customer_1.json'))->Customer;
    }

    public function getReader(QuickBooksApi $api, QuickBooksCreditMemoTransformer $transformer, ?LoaderInterface $loader = null): QuickBooksCreditMemoReader
    {
        $loaderFactory = $loader ? $this->getLoader($loader) : self::getService('test.accounting_loader_factory');
        $extractorFactory = Mockery::mock(QuickBooksExtractorFactory::class);
        $extractorFactory->shouldReceive('get')->andReturn(new QuickBooksCreditMemoExtractor($api));
        $transformerFactory = Mockery::mock(QuickBooksTransformerFactory::class);
        $transformerFactory->shouldReceive('get')->andReturn($transformer);

        return new QuickBooksCreditMemoReader(self::getService('test.transaction_manager'), $extractorFactory, $transformerFactory, $loaderFactory);
    }

    private function getLoader(LoaderInterface $loader): AccountingLoaderFactory
    {
        $loaderFactory = Mockery::mock(AccountingLoaderFactory::class);
        $loaderFactory->shouldReceive('get')
            ->andReturn($loader);

        return $loaderFactory;
    }

    public function testGetId(): void
    {
        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $transformer = new QuickBooksCreditMemoTransformer($quickBooksApi);
        $reader = $this->getReader($quickBooksApi, $transformer);
        $this->assertEquals('quickbooks_online_credit_note', $reader->getId());
    }

    public function testSuccessfulSync(): void
    {
        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $quickBooksApi->shouldReceive('setAccount');
        $quickBooksApi->shouldReceive([
            'getCustomer' => self::$testCustomer,
        ]);
        $quickBooksApi->shouldReceive(['query' => self::$testCreditNoteQueries[0]])
            ->withArgs([QuickBooksApi::CREDIT_NOTE, 1, "MetaData.LastUpdatedTime > '2021-11-14T00:00:00+00:00' AND TxnDate >= '2015-03-19'"])
            ->once();
        $quickBooksApi->shouldReceive(['query' => []])
            ->withArgs([QuickBooksApi::CREDIT_NOTE, 2, "MetaData.LastUpdatedTime > '2021-11-14T00:00:00+00:00' AND TxnDate >= '2015-03-19'"])
            ->once();

        // Include reconciliation error (to test the deletion after success)
        $error = new ReconciliationError();
        $error->object = ObjectType::Invoice->typeName();
        $error->accounting_id = self::$testCreditNoteQueries[0][0]->Id;
        $error->integration_id = IntegrationType::QuickBooksOnline->value;
        $error->retry_context = (object) [
            'object' => 'credit_note',
            'accountingId' => self::$testCreditNoteQueries[0][0]->Id,
        ];
        $error->saveOrFail();

        $transformer = new QuickBooksCreditMemoTransformer($quickBooksApi);

        $query = new ReadQuery(new CarbonImmutable('2021-11-14'), new CarbonImmutable('2015-03-19'));

        $reader = $this->getReader($quickBooksApi, $transformer);
        $reader->syncAll(self::$quickbooksAccount, self::$syncProfile, $query);

        // Test that error has been removed.
        $error = ReconciliationError::where('object', 'credit_note')
            ->where('accounting_id', self::$testCreditNoteQueries[0][0]->Id)
            ->oneOrNull();
        $this->assertNull($error);

        // Test that a mapping has been created for the invoice (done through reader)
        $mapping = AccountingCreditNoteMapping::where('accounting_id', self::$testCreditNoteQueries[0][0]->Id)
            ->where('integration_id', IntegrationType::QuickBooksOnline->value)
            ->oneOrNull();
        $this->assertNotNull($mapping);
    }

    public function testLoadException(): void
    {
        $testExceptionMessage = 'SAMPLE: Invalid record';

        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $quickBooksApi->shouldReceive('setAccount');
        $quickBooksApi->shouldReceive([
            'getCustomer' => self::$testCustomer,
        ]);
        $quickBooksApi->shouldReceive(['query' => self::$testCreditNoteQueries[1]])
            ->withArgs([QuickBooksApi::CREDIT_NOTE, 1, "MetaData.LastUpdatedTime > '2021-11-14T00:00:00+00:00' AND TxnDate >= '2015-03-19'"])
            ->once();
        $quickBooksApi->shouldReceive(['query' => []])
            ->withArgs([QuickBooksApi::CREDIT_NOTE, 2, "MetaData.LastUpdatedTime > '2021-11-14T00:00:00+00:00' AND TxnDate >= '2015-03-19'"])
            ->once();
        $transformer = Mockery::mock(new QuickBooksCreditMemoTransformer($quickBooksApi))->makePartial();
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')
            ->andThrow(new LoadException($testExceptionMessage));

        $query = new ReadQuery(new CarbonImmutable('2021-11-14'), new CarbonImmutable('2015-03-19'));

        $reader = $this->getReader($quickBooksApi, $transformer, $loader);
        $reader->syncAll(self::$quickbooksAccount, self::$syncProfile, $query);

        // Test that reconciliation error was created on import failure
        /** @var ReconciliationError $error */
        $error = ReconciliationError::where('accounting_id', self::$testCreditNoteQueries[1][0]->Id)
            ->where('integration_id', IntegrationType::QuickBooksOnline->value)
            ->oneOrNull();
        $this->assertNotNull($error);

        // Validate retry event
        $context = (new RetryContextFactory())->make($error);
        $this->assertEquals([
            'accountingId' => self::$testCreditNoteQueries[1][0]->Id,
            'object' => 'credit_note',
            'invoicedId' => null,
            'reader' => 'quickbooks_online_credit_note',
            'accounting_id' => self::$testCreditNoteQueries[1][0]->Id,
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
            ->withArgs([QuickBooksApi::CREDIT_NOTE, 1, "MetaData.LastUpdatedTime > '2021-11-14T00:00:00+00:00' AND TxnDate >= '2021-04-26'"])
            ->andReturn([]); // Only testing the query, so the results are not important.

        $transformer = new QuickBooksCreditMemoTransformer($quickBooksApi);

        $query = new ReadQuery(new CarbonImmutable('2021-11-14'), new CarbonImmutable('2021-04-26'));

        $reader = $this->getReader($quickBooksApi, $transformer);
        $reader->syncAll(self::$quickbooksAccount, self::$syncProfile, $query);
    }
}
