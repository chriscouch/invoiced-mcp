<?php

namespace App\Tests\Integrations\QuickBooksOnline\Writers;

use App\AccountsReceivable\Models\Customer;
use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksCustomerWriter;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class QuickBooksCustomerWriterTest extends AppTestCase
{
    private static QuickBooksOnlineSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::$syncProfile = new QuickBooksOnlineSyncProfile();
        self::$syncProfile->write_customers = true;
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->saveOrFail();
    }

    /**
     * Returns instance of QuickBooksCustomerWriter configured
     * for test cases.
     */
    public function getWriter(QuickBooksApi $api): QuickBooksCustomerWriter
    {
        $writer = new QuickBooksCustomerWriter($api);
        $writer->setStatsd(new StatsdClient());

        return $writer;
    }

    public function testIsEnabled(): void
    {
        $writer = $this->getWriter(Mockery::mock(QuickBooksApi::class));
        $this->assertFalse($writer->isEnabled(new QuickBooksOnlineSyncProfile(['write_customers' => false])));
        $this->assertTrue($writer->isEnabled(new QuickBooksOnlineSyncProfile(['write_customers' => true])));
    }

    /**
     * Tests QuickBooksCustomerWriter::create functionality after
     * a successful API call to create customer on QBO.
     */
    public function testCreateSuccess(): void
    {
        // configure api.
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        // The actual QBO response will return more than just the Id but the Id
        // is the only element used from the response.
        $quickbooksApi->shouldReceive([
            'getCustomerByName' => null,
            'createCustomer' => (object) [
                    'Id' => 1,
            ],
            'setAccount' => null,
        ]);

        $writer = $this->getWriter($quickbooksApi);
        $writer->create(self::$customer, self::$quickbooksAccount, self::$syncProfile);

        /** @var AccountingCustomerMapping $mapping */
        $mapping = AccountingCustomerMapping::find(self::$customer->id());
        $this->assertNotNull($mapping);
        $this->assertEquals(1, $mapping->accounting_id);

        $this->cleanCustomerData(self::$customer); // clean data for future tests.
    }

    /**
     * Tests QuickBooksCustomerWriter::create functionality after
     * a failed API call to create a customer on QBO.
     */
    public function testCreateFailure(): void
    {
        // test exception message.
        $exceptionMessage = 'An error occurred while creating the customer on QBO.';

        // configure api.
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $quickbooksApi->shouldReceive('setAccount');
        $quickbooksApi->shouldReceive('getCustomerByName')
            ->andReturn(null);
        $quickbooksApi->shouldReceive('createCustomer')
            ->andThrow(IntegrationApiException::class, $exceptionMessage); // throw an error to test exception handling.

        $writer = $this->getWriter($quickbooksApi);
        $writer->create(self::$customer, self::$quickbooksAccount, self::$syncProfile); // should catch and handle a IntegrationApiException

        // look for reconciliation error
        $reconciliationError = ReconciliationError::where('object', 'customer')
            ->where('object_id', self::$customer)
            ->oneOrNull();

        $this->assertNotNull($reconciliationError);
        $this->assertEquals($exceptionMessage, $reconciliationError->message);

        $reconciliationError->delete(); // delete error for future test cases.
    }

    /**
     * Tests QuickBooksCustomerWriter::update functionality.
     */
    public function testUpdateSuccess(): void
    {
        // create a mapping to allow writer->update to use update functionality.
        $this->createMapping(self::$customer);

        // configure api.
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        // The actual QBO response will return more than just the Id and SyncToken but only those two
        // elements are used from the response.
        $quickbooksApi->shouldReceive([
            'getCustomer' => (object) [
                'Id' => 1,
                'SyncToken' => 0,
            ],
            'updateCustomer' => (object) [
                    'Id' => 1,
            ], // Data returned from this request isn't used in writer->update.
            'setAccount' => null,
        ]);

        $writer = $this->getWriter($quickbooksApi);
        $writer->update(self::$customer, self::$quickbooksAccount, self::$syncProfile);

        $error = ReconciliationError::where('object', 'customer')
            ->where('object_id', self::$customer)
            ->oneOrNull();
        $this->assertNull($error);

        $this->cleanCustomerData(self::$customer); // clean data for future tests.
    }

    /**
     * Tests QuickBooksCustomerWriter::update functionality after
     * a failed API call to create a find/update on QBO.
     */
    public function testUpdateFailure(): void
    {
        // create a mapping to allow writer->update to use update functionality.
        $this->createMapping(self::$customer);

        // test exception message.
        $exceptionMessage = 'An error occurred while finding the customer on QBO.';

        // configure api.
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $quickbooksApi->shouldReceive('setAccount');
        $quickbooksApi->shouldReceive('getCustomer')
            ->andThrow(IntegrationApiException::class, $exceptionMessage); // throw an error to test exception handling.

        $writer = $this->getWriter($quickbooksApi);
        $writer->update(self::$customer, self::$quickbooksAccount, self::$syncProfile);

        // look for reconciliation error
        $reconciliationError = ReconciliationError::where('object', 'customer')
            ->oneOrNull();

        $this->assertNotNull($reconciliationError);
        $this->assertEquals($exceptionMessage, $reconciliationError->message);

        $reconciliationError->delete(); // delete error for future test cases.
    }

    /**
     * Creates an accounting mapping for a customer.
     * Used for tests that need a mapping as a requirement
     * for testing the functionality.
     */
    private function createMapping(Customer $customer): AccountingCustomerMapping
    {
        $mapping = new AccountingCustomerMapping();
        $mapping->customer = $customer;
        $mapping->accounting_id = '1';
        $mapping->source = AccountingCustomerMapping::SOURCE_INVOICED;
        $mapping->integration_id = IntegrationType::QuickBooksOnline->value;
        $mapping->saveOrFail();

        return $mapping;
    }

    /**
     * Removes customer accounting mapping and metadata.
     * Used to keep test cases separate.
     */
    private function cleanCustomerData(Customer $customer): void
    {
        // delete the mapping to allow future test cases to operate correctly.
        $mapping = AccountingCustomerMapping::find($customer->id());
        if ($mapping) {
            $mapping->delete();
        }

        // delete customer metadata to allow future test cases to operate correctly.
        self::$customer->metadata = (object) [];
        self::$customer->save();
    }
}
