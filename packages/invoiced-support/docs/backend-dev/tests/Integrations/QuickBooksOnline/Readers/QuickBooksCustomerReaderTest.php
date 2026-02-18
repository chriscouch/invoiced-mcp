<?php

namespace App\Tests\Integrations\QuickBooksOnline\Readers;

use App\AccountsReceivable\Models\Customer;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\RetryContextFactory;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksCustomerExtractor;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksExtractorFactory;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Readers\QuickBooksCustomerReader;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksCustomerTransformer;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksTransformerFactory;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class QuickBooksCustomerReaderTest extends AppTestCase
{
    private static string $jsonDIR;
    private static QuickBooksOnlineSyncProfile $syncProfile;
    private static array $testQueries;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasQuickBooksAccount();
        self::$syncProfile = new QuickBooksOnlineSyncProfile();
        self::$syncProfile->read_cursor = (new CarbonImmutable('2021-11-14'))->getTimestamp();
        self::$syncProfile->read_customers = true;
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->save();

        self::$jsonDIR = dirname(__DIR__).'/json/quickbooks_customer_importer';
        self::$testQueries = [
            json_decode((string) file_get_contents(self::$jsonDIR.'/quickbooks_customer_importer_customers_1.json'))->QueryResponse->Customer,
            json_decode((string) file_get_contents(self::$jsonDIR.'/quickbooks_customer_importer_customers_2.json'))->QueryResponse->Customer,
            json_decode((string) file_get_contents(self::$jsonDIR.'/quickbooks_customer_importer_customers_3.json'))->QueryResponse->Customer,
        ];
    }

    private function getReader(QuickBooksApi $api, QuickBooksCustomerTransformer $transformer, ?LoaderInterface $loader = null): QuickBooksCustomerReader
    {
        $loaderFactory = $loader ? $this->getLoader($loader) : self::getService('test.accounting_loader_factory');
        $extractorFactory = Mockery::mock(QuickBooksExtractorFactory::class);
        $extractorFactory->shouldReceive('get')->andReturn(new QuickBooksCustomerExtractor($api));
        $transformerFactory = Mockery::mock(QuickBooksTransformerFactory::class);
        $transformerFactory->shouldReceive('get')->andReturn($transformer);

        return new QuickBooksCustomerReader(self::getService('test.transaction_manager'), $extractorFactory, $transformerFactory, $loaderFactory);
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
        $transformer = Mockery::mock(new QuickBooksCustomerTransformer());
        $reader = $this->getReader($quickBooksApi, $transformer);
        $this->assertEquals('quickbooks_online_customer', $reader->getId());
    }

    public function testSuccessfulSync(): void
    {
        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $quickBooksApi->shouldReceive('setAccount');
        $quickBooksApi->shouldReceive([
            'query' => self::$testQueries[0],
        ])->once();
        $quickBooksApi->shouldReceive([
            'query' => [],
        ])->once();

        // Include reconciliation error (to test the deletion after success)
        $error = new ReconciliationError();
        $error->object = ObjectType::Customer->typeName();
        $error->accounting_id = self::$testQueries[0][0]->Id;
        $error->integration_id = IntegrationType::QuickBooksOnline->value;
        $error->retry_context = (object) [
            'object' => ObjectType::Customer->typeName(),
            'accountingId' => self::$testQueries[0][0]->Id,
        ];
        $error->saveOrFail();

        $query = new ReadQuery(new CarbonImmutable('2021-11-14'), new CarbonImmutable('2015-03-19'));

        $reader = $this->getReader($quickBooksApi, new QuickBooksCustomerTransformer());
        $reader->syncAll(self::$quickbooksAccount, self::$syncProfile, $query);

        // Test that reconciliation error was removed
        $error = ReconciliationError::where('accounting_id', self::$testQueries[0][0]->Id)
            ->where('integration_id', IntegrationType::QuickBooksOnline->value)
            ->oneOrNull();
        $this->assertNull($error);

        // Test that mapping was created
        $mapping = AccountingCustomerMapping::where('accounting_id', self::$testQueries[0][0]->Id)
            ->where('integration_id', IntegrationType::QuickBooksOnline->value)
            ->oneOrNull();
        $this->assertNotNull($mapping);

        // Test that customer was imported
        /** @var Customer $importedCustomer */
        $importedCustomer = Customer::find($mapping->customer_id);
        $this->assertNotNull($importedCustomer);
        $this->assertEquals('Test', $importedCustomer->name);
        $this->assertEquals('test@example.com', $importedCustomer->email);
        $this->assertEquals('(123) 456-7890', $importedCustomer->phone);
    }

    public function testLoadException(): void
    {
        $testExceptionMessage = 'SAMPLE: Invalid record';

        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $quickBooksApi->shouldReceive('setAccount');
        $quickBooksApi->shouldReceive([
            'query' => self::$testQueries[1],
        ])->once();
        $quickBooksApi->shouldReceive([
            'query' => [],
        ])->once();

        $transformer = Mockery::mock(new QuickBooksCustomerTransformer())->makePartial();
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')
            ->andThrow(new LoadException($testExceptionMessage));

        $query = new ReadQuery(new CarbonImmutable('2021-11-14'), new CarbonImmutable('2015-03-19'));

        $reader = $this->getReader($quickBooksApi, $transformer, $loader);
        $reader->syncAll(self::$quickbooksAccount, self::$syncProfile, $query);

        // Test that reconciliation error was created on import failure
        /** @var ReconciliationError $error */
        $error = ReconciliationError::where('accounting_id', self::$testQueries[1][0]->Id)
            ->where('integration_id', IntegrationType::QuickBooksOnline->value)
            ->oneOrNull();
        $this->assertNotNull($error);

        // Validate retry event
        $context = (new RetryContextFactory())->make($error);
        $this->assertEquals([
            'accountingId' => self::$testQueries[1][0]->Id,
            'object' => 'customer',
            'invoicedId' => null,
            'reader' => 'quickbooks_online_customer',
            'accounting_id' => self::$testQueries[1][0]->Id,
            'object_id' => null,
        ], $context?->data);
    }
}
