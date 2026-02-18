<?php

namespace App\Tests\Integrations\Intacct\Writers;

use App\AccountsReceivable\Models\Customer;
use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Writers\IntacctCustomerWriter;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Intacct\Functions\AccountsReceivable\CustomerCreate;
use Intacct\Functions\AccountsReceivable\CustomerUpdate;
use Mockery;

class IntacctCustomerWriterTest extends AppTestCase
{
    private static IntacctSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();

        self::hasIntacctAccount();
        self::$syncProfile = new IntacctSyncProfile();
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->saveOrFail();
    }

    private function getWriter(IntacctApi $intacctApi): IntacctCustomerWriter
    {
        $writer = new IntacctCustomerWriter($intacctApi);
        $writer->setStatsd(new StatsdClient());
        $writer->setLogger(self::$logger);

        return $writer;
    }

    public function testIsEnabled(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $writer = $this->getWriter($intacctApi);
        $this->assertFalse($writer->isEnabled(new IntacctSyncProfile(['write_customers' => false])));
        $this->assertTrue($writer->isEnabled(new IntacctSyncProfile(['write_customers' => true])));
    }

    public function testCreateIntacctException(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('getCustomerByNumber')->andReturn(null);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createTopLevelObject')
            ->andThrow(new IntegrationApiException('test'));

        $writer = $this->getWriter($intacctApi);

        $writer->create(self::$customer, self::$intacctAccount, self::$syncProfile);

        // should create a reconciliation error
        $error = ReconciliationError::where('object', 'customer')
            ->where('object_id', self::$customer)
            ->oneOrNull();

        $this->assertInstanceOf(ReconciliationError::class, $error);
        $this->assertEquals('test', $error->message);
        $this->assertEquals(IntegrationType::Intacct->value, $error->integration_id);
        $this->assertEquals(ReconciliationError::LEVEL_ERROR, $error->level);
    }

    public function testCreate(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('getCustomerByNumber')->andReturn(null);
        $intacctApi->shouldReceive('createTopLevelObject')
            ->andReturnUsing(function (CustomerCreate $intacctCustomer) {
                $this->assertEquals(self::$customer->name, $intacctCustomer->getCustomerName());
                $this->assertEquals(self::$customer->email, $intacctCustomer->getPrimaryEmailAddress());
                $this->assertEquals(self::$customer->phone, $intacctCustomer->getPrimaryPhoneNo());
                $this->assertEquals(self::$customer->number, $intacctCustomer->getCustomerId());
                $this->assertEquals(self::$customer->address1, $intacctCustomer->getAddressLine1());
                $this->assertEquals(self::$customer->address2, $intacctCustomer->getAddressLine2());
                $this->assertEquals(self::$customer->city, $intacctCustomer->getCity());
                $this->assertEquals(self::$customer->state, $intacctCustomer->getStateProvince());
                $this->assertEquals(self::$customer->country, $intacctCustomer->getIsoCountryCode());

                return '1234';
            })
            ->once();

        $writer = $this->getWriter($intacctApi);

        $writer->create(self::$customer, self::$intacctAccount, self::$syncProfile);

        /** @var AccountingCustomerMapping $mapping */
        $mapping = AccountingCustomerMapping::find(self::$customer->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testUpdate(): void
    {
        self::$customer->name = 'Test Update';
        self::$customer->saveOrFail();

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createTopLevelObject')
            ->andReturnUsing(function (CustomerUpdate $intacctCustomer) {
                $this->assertEquals(self::$customer->name, $intacctCustomer->getCustomerName());
                $this->assertEquals(self::$customer->email, $intacctCustomer->getPrimaryEmailAddress());
                $this->assertEquals(self::$customer->phone, $intacctCustomer->getPrimaryPhoneNo());
                $this->assertEquals(self::$customer->number, $intacctCustomer->getCustomerId());
                $this->assertEquals(self::$customer->address1, $intacctCustomer->getAddressLine1());
                $this->assertEquals(self::$customer->address2, $intacctCustomer->getAddressLine2());
                $this->assertEquals(self::$customer->city, $intacctCustomer->getCity());
                $this->assertEquals(self::$customer->state, $intacctCustomer->getStateProvince());
                $this->assertEquals(self::$customer->country, $intacctCustomer->getIsoCountryCode());

                return '1234';
            })
            ->once();

        $writer = $this->getWriter($intacctApi);

        $writer->update(self::$customer, self::$intacctAccount, self::$syncProfile);

        /** @var AccountingCustomerMapping $mapping */
        $mapping = AccountingCustomerMapping::find(self::$customer->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testUpdateNewMapping(): void
    {
        $customer = new Customer();
        $customer->name = 'New Customer';
        $customer->saveOrFail();

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('getCustomerByNumber')->andReturn(null);
        $intacctApi->shouldReceive('createTopLevelObject')
            ->andReturnUsing(function (CustomerCreate $intacctCustomer) use ($customer) {
                $this->assertEquals('New Customer', $intacctCustomer->getCustomerName());
                $this->assertNull($intacctCustomer->getPrimaryEmailAddress());
                $this->assertNull($intacctCustomer->getPrimaryPhoneNo());
                $this->assertEquals($customer->number, $intacctCustomer->getCustomerId());
                $this->assertNull($intacctCustomer->getAddressLine1());
                $this->assertNull($intacctCustomer->getAddressLine2());
                $this->assertNull($intacctCustomer->getCity());
                $this->assertNull($intacctCustomer->getStateProvince());
                $this->assertEquals('US', $intacctCustomer->getIsoCountryCode());

                return '1234';
            })
            ->once();

        $writer = $this->getWriter($intacctApi);

        $writer->update($customer, self::$intacctAccount, self::$syncProfile);

        /** @var AccountingCustomerMapping $mapping */
        $mapping = AccountingCustomerMapping::find($customer->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testUpdateFromIntacct(): void
    {
        $customer = new Customer();
        $customer->name = 'New Customer';
        $customer->saveOrFail();

        $mapping = new AccountingCustomerMapping();
        $mapping->customer = $customer;
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '1234';
        $mapping->source = AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        // should not receive any other method calls

        $writer = $this->getWriter($intacctApi);

        $writer->update($customer, self::$intacctAccount, self::$syncProfile);
    }

    public function testCreateCustomFieldMapping(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('getCustomerByNumber')->andReturn(null);
        $intacctApi->shouldReceive('createTopLevelObject')
            ->andReturnUsing(function (CustomerCreate $intacctCustomer) {
                $expected = [
                    'DEPARTMENT' => 'Sales',
                    'PHONE' => '123456789',
                ];
                $this->assertEquals($expected, $intacctCustomer->getCustomFields());

                return '1235';
            })
            ->once();

        self::$syncProfile->customer_custom_field_mapping = (object) [
            'phone' => 'PHONE',
            'metadata.department' => 'DEPARTMENT',
            'metadata.does_not_exist' => 'SHOULD_NOT_BE_SET',
        ];
        self::$syncProfile->saveOrFail();

        $customer = new Customer();
        $customer->name = 'Test';
        $customer->phone = '123456789';
        $customer->metadata = (object) ['department' => 'Sales'];
        $customer->saveOrFail();
        $writer = $this->getWriter($intacctApi);

        $writer->create($customer, self::$intacctAccount, self::$syncProfile);
    }
}
