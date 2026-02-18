<?php

namespace App\Tests\Imports\Importers;

use App\AccountsReceivable\Models\Customer;
use App\Core\Utils\Enums\ObjectType;
use App\Imports\Importers\Spreadsheet\CustomerImporter;
use App\Imports\Models\Import;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\Enums\IntegrationType;
use App\Metadata\Models\CustomField;
use Mockery;

class CustomerImporterTest extends ImporterTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$customer = new Customer();
        self::$customer->name = 'Old';
        self::$customer->country = 'US';
        self::$customer->number = 'CUST-0003';
        self::$customer->saveOrFail();

        $customField1 = new CustomField();
        $customField1->object = ObjectType::Customer->typeName();
        $customField1->id = 'custom_field_boolean';
        $customField1->name = 'Custom Field Boolean';
        $customField1->type = 'boolean';
        $customField1->saveOrFail();

        self::hasLateFeeSchedule();
    }

    protected function getImporter(): CustomerImporter
    {
        return self::getService('test.importer_factory')->get('customer');
    }

    public function testRunCreate(): void
    {
        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $import = $this->getImport();

        $records = $importer->build($mapping, $lines, [], $import);
        $result = $importer->run($records, [], $import);

        // verify result
        $this->assertEquals(2, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(0, $result->getNumUpdated(), (string) json_encode($result->getFailures()));

        // should create a customer named "Test"
        /** @var Customer $customer */
        $customer = Customer::where('name', 'Test')->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('US', $customer->country);
        $this->assertEquals((object) [
            'test' => '1234',
            'custom_field_boolean' => false,
            ], $customer->metadata);
        $this->assertEquals(false, $customer->active);

        // should create an accounting mapping
        $mapping = AccountingCustomerMapping::find($customer->id);
        $this->assertInstanceOf(AccountingCustomerMapping::class, $mapping);
        $this->assertEquals(IntegrationType::Intacct, $mapping->getIntegrationType());
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertEquals('accounting_system', $mapping->source);

        // should create a customer numbered "CUST-0002"
        $customer2 = Customer::where('number', 'CUST-0002')->oneOrNull();
        $this->assertInstanceOf(Customer::class, $customer2);

        // should update the position
        $this->assertEquals(5, $import->position);
    }

    public function testRunUpsert(): void
    {
        // add metadata to an existing customer
        $customer = Customer::where('name', 'Test')->one();
        $customer->metadata = (object) ['test' => 'hello', 'test2' => 'do not overwrite me'];
        $customer->saveOrFail();

        self::$customer->metadata = (object) array_merge(['test' => 'hello'], (array) self::$customer->metadata);
        self::$customer->saveOrFail();

        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];

        $records = $importer->build($mapping, $lines, $options, $import);
        $result = $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals(0, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(4, $result->getNumUpdated(), (string) json_encode($result->getFailures()));

        // should update a customer numbered "CUST-0003"
        self::$customer->refresh();
        $this->assertEquals('Test 2', self::$customer->name);
        $this->assertEquals(['tax-rate'], self::$customer->taxes);
        $this->assertEquals('hello', self::$customer->metadata->test);

        // should update a customer named "Test"
        $customer = Customer::where('name', 'Test')->one();
        $this->assertEquals('1234', $customer->metadata->test);
        $this->assertEquals('do not overwrite me', $customer->metadata->test2);

        // should update the position
        $this->assertEquals(5, $import->position);
    }

    public function testRunDelete(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Delete Test';
        $customer->country = 'US';
        $customer->saveOrFail();

        $customer2 = new Customer();
        $customer2->name = 'Delete Test 2';
        $customer2->country = 'US';
        $customer2->saveOrFail();

        $mapping = ['name', 'number'];
        $lines = [
            [
                'Delete Test',
                '',
            ],
            [
                '',
                $customer2->number,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'delete'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertNull(Customer::where('name', 'Delete Test')->oneOrNull());
        $this->assertNull(Customer::where('number', $customer2->number)->oneOrNull());
    }

    public function testRunLateFeeSchedules(): void
    {
        $importer = $this->getImporter();

        $mapping = [
            'name',
            'late_fee_schedule',
        ];
        $lines = [
            [
                'late fee test1',
                'My Late Fee Schedule',
            ],
            [
                'late fee test2',
                '',
            ],
        ];
        $import = $this->getImport();

        $records = $importer->build($mapping, $lines, [], $import);
        $result = $importer->run($records, [], $import);

        // verify result
        $this->assertEquals(2, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(0, $result->getNumUpdated(), (string) json_encode($result->getFailures()));

        $customers = Customer::where('name LIKE "%late fee%"')->sort('name ASC')->all();
        $this->assertCount(2, $customers);
        $this->assertEquals(self::$lateFeeSchedule->id(), $customers[0]->late_fee_schedule_id);
        $this->assertNull($customers[1]->late_fee_schedule_id);
    }

    protected function getLines(): array
    {
        return [
            [
                'Test',
                '',
                '1234',
                0,
                '',
                'Texas',
                0,
                'intacct',
                '1234',
            ],
            [
                'Test 2',
                'CUST-0002',
            ],
            [
                'Test 2',
                'CUST-0003',
                '',
                1,
                'tax-rate',
            ],
            [
                'Test Diff Name',
                'CUST-0002',
            ],
            [],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'name',
            'number',
            'metadata.test',
            'metadata.custom_field_boolean',
            'taxes',
            'state',
            'active',
            'accounting_system',
            'accounting_id',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'customer';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'name' => 'Test',
                'emails' => [],
                'taxes' => [],
                'state' => 'TX',
                'metadata' => (object) [
                    'test' => '1234',
                    'custom_field_boolean' => 0,
                ],
                'active' => false,
                'accounting_system' => IntegrationType::Intacct,
                'accounting_id' => '1234',
            ],
            [
                '_operation' => 'create',
                'name' => 'Test 2',
                'number' => 'CUST-0002',
                'emails' => [],
                'taxes' => [],
                'state' => null,
                'metadata' => (object) [
                    'custom_field_boolean' => null,
                    'test' => null,
                ],
                'accounting_system' => null,
                'accounting_id' => null,
            ],
            [
                '_operation' => 'create',
                'name' => 'Test 2',
                'number' => 'CUST-0003',
                'emails' => [],
                'taxes' => ['tax-rate'],
                'state' => null,
                'metadata' => (object) [
                    'custom_field_boolean' => 1,
                    'test' => null,
                ],
                'accounting_system' => null,
                'accounting_id' => null,
            ],
            [
                '_operation' => 'create',
                'name' => 'Test Diff Name',
                'number' => 'CUST-0002',
                'emails' => [],
                'taxes' => [],
                'state' => null,
                'metadata' => (object) [
                    'custom_field_boolean' => null,
                    'test' => null,
                ],
                'accounting_system' => null,
                'accounting_id' => null,
            ],
            [
                '_operation' => 'create',
                'name' => null,
                'number' => null,
                'emails' => [],
                'taxes' => [],
                'state' => null,
                'metadata' => (object) [
                    'custom_field_boolean' => null,
                    'test' => null,
                ],
                'accounting_system' => null,
                'accounting_id' => null,
            ],
        ];
    }
}
