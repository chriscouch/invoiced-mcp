<?php

namespace App\Tests\Integrations\AccountingSync\Loaders;

use App\AccountsReceivable\Models\Customer;
use App\Core\Orm\Event\ModelCreating;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Loaders\AccountingCustomerLoader;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;

class AccountingCustomerLoaderTest extends AppTestCase
{
    private static int $originalId = -1;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getLoader(): AccountingCustomerLoader
    {
        return self::getService('test.accounting_customer_loader');
    }

    public function testLoadNewCustomer(): void
    {
        $loader = $this->getLoader();

        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: '1',
            values: [
                'name' => 'Test Customer',
            ],
        );

        $result = $loader->load($accountingCustomer);

        $this->assertTrue($result->wasCreated());
        /** @var Customer $customer */
        $customer = $result->getModel();
        $this->assertEquals('Test Customer', $customer->name);
        self::$originalId = $customer->id;

        // should create mapping
        $mapping = AccountingCustomerMapping::findOrFail($customer->id);
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1', $mapping->accounting_id);
        $this->assertEquals(AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM, $mapping->source);
    }

    /**
     * @depends testLoadNewCustomer
     */
    public function testLoadUpdateCustomerByAccountingId(): void
    {
        $loader = $this->getLoader();

        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: '1',
            values: [
                'name' => 'Rename Customer',
            ],
        );

        $result = $loader->load($accountingCustomer);

        // should update the customer
        $this->assertTrue($result->wasUpdated());
        /** @var Customer $customer */
        $customer = $result->getModel();
        $this->assertEquals('Rename Customer', $customer->name);
        $this->assertEquals(self::$originalId, $customer->id);
    }

    /**
     * @depends testLoadNewCustomer
     */
    public function testLoadUpdateCustomerByNumber(): void
    {
        $loader = $this->getLoader();

        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: '2',
            values: [
                'name' => 'Rename Customer (2)',
                'number' => 'CUST-00001',
            ],
            emails: ['test@example.com', 'secondary@example.com'],
        );

        $result = $loader->load($accountingCustomer);

        // should update the customer
        $this->assertTrue($result->wasUpdated());
        /** @var Customer $customer */
        $customer = $result->getModel();
        $this->assertEquals('Rename Customer (2)', $customer->name);
        $this->assertEquals(self::$originalId, $customer->id);
        $this->assertEquals('test@example.com', $customer->email);
        $expected = [
            ['name' => 'Rename Customer (2)', 'email' => 'secondary@example.com'],
            ['name' => 'Rename Customer (2)', 'email' => 'test@example.com'],
        ];
        $this->assertEquals($expected, $customer->emailContacts());

        // should update the mapping with new ID
        $mapping = AccountingCustomerMapping::findOrFail($customer->id);
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('2', $mapping->accounting_id);
        $this->assertEquals(AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM, $mapping->source);
    }

    /**
     * @depends testLoadNewCustomer
     */
    public function testLoadUpdateCustomerByName(): void
    {
        $loader = $this->getLoader();

        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: '3',
            values: [
                'name' => 'Rename Customer (2)',
                'active' => false,
            ],
            contacts: [
                [
                    'name' => 'Contact Name one',
                    'email' => 'contact@name.one',
                    'primary' => true,
                ],
                [
                    'name' => 'Contact Name two',
                    'email' => 'contact@name.two',
                    'primary' => false,
                ],
            ],
        );

        $result = $loader->load($accountingCustomer);

        // should update the customer
        $this->assertTrue($result->wasUpdated());
        /** @var Customer $customer */
        $customer = $result->getModel();
        $this->assertFalse($customer->active);
        $this->assertEquals(self::$originalId, $customer->id);
        $this->assertEquals([
            [
                'name' => 'Contact Name one',
                'email' => 'contact@name.one',
                'primary' => true,
            ],
            [
                'name' => 'Contact Name two',
                'email' => 'contact@name.two',
                'primary' => false,
            ],
            [
                'name' => 'Rename Customer (2)',
                'email' => 'test@example.com',
                'primary' => true,
            ],
        ], array_map(fn ($contact) => array_intersect_key($contact->toArray(), [
            'name' => null,
            'email' => 'test@example.com',
            'primary' => null,
        ]), $customer->contacts(false)));

        // should update the mapping with new ID
        $mapping = AccountingCustomerMapping::findOrFail($customer->id);
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('3', $mapping->accounting_id);
        $this->assertEquals(AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM, $mapping->source);
    }

    public function testLoadSourceFromInvoiced(): void
    {
        $customer = new Customer();
        $customer->name = 'INVD-3080';
        $customer->saveOrFail();

        $mapping = new AccountingCustomerMapping();
        $mapping->customer = $customer;
        $mapping->integration_id = IntegrationType::QuickBooksOnline->value;
        $mapping->accounting_id = '1234';
        $mapping->source = AccountingCustomerMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $loader = $this->getLoader();

        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: '1234',
            values: [
                'name' => 'INVD-3080',
                'notes' => 'QuickBooks Notes',
            ],
        );

        $result = $loader->load($accountingCustomer);

        // should NOT update the customer because the source belongs to Invoiced
        $this->assertNull($result->getAction());
        $this->assertNotNull($result->getModel());

        /** @var Customer $customer2 */
        $customer2 = $result->getModel();
        $this->assertNull($customer2->notes);
        $this->assertEquals($customer->id, $customer2->id);

        // should NOT update the mapping source
        $this->assertEquals(IntegrationType::QuickBooksOnline->value, $mapping->refresh()->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertEquals(AccountingCustomerMapping::SOURCE_INVOICED, $mapping->source);
    }

    public function testLoadDeletedCustomer(): void
    {
        $customer = new Customer();
        $customer->name = 'delete_1';
        $customer->saveOrFail();

        $mapping = new AccountingCustomerMapping();
        $mapping->customer = $customer;
        $mapping->integration_id = IntegrationType::QuickBooksOnline->value;
        $mapping->accounting_id = 'delete_1';
        $mapping->source = AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        $loader = $this->getLoader();

        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'delete_1',
            deleted: true
        );

        $result = $loader->load($accountingCustomer);

        // should delete the customer
        $this->assertTrue($result->wasDeleted());
        $this->assertNotNull($result->getModel());
        $this->assertNull(Customer::find($customer->id()));

        $accountingCustomer2 = new AccountingCustomer(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'delete_2',
            deleted: true
        );

        $result = $loader->load($accountingCustomer2);

        // should do nothing because the customer is not existing
        $this->assertNull($result->getAction());
        $this->assertNull($result->getModel());
    }

    public function testLoadParentCustomer(): void
    {
        $loader = $this->getLoader();

        // load parent customer
        $parentCustomer = new AccountingCustomer(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'parent_1',
            values: ['name' => 'Parent Customer'],
        );

        $result = $loader->load($parentCustomer);

        $this->assertEquals($result::CREATE, $result->getAction());
        $customer = $result->getModel();
        $this->assertInstanceOf(Customer::class, $customer);

        // load child customer
        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'child_1',
            values: ['name' => 'Child Customer'],
            parentCustomer: $parentCustomer,
        );

        $result = $loader->load($accountingCustomer);

        $this->assertEquals($result::CREATE, $result->getAction());
        $customer2 = $result->getModel();
        $this->assertInstanceOf(Customer::class, $customer2);
        $this->assertEquals($customer->id, $customer2->parent_customer);

        // INVD-3257: loading child customer again without parent customer should NOT clear the association
        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'child_1',
            values: ['name' => 'Child Customer'],
        );

        $result = $loader->load($accountingCustomer);

        $this->assertEquals($result::UPDATE, $result->getAction());
        $customer2 = $result->getModel();
        $this->assertInstanceOf(Customer::class, $customer2);
        $this->assertEquals($customer->id, $customer2->parent_customer);
    }

    public function testLoadParentCustomerSameRequest(): void
    {
        self::hasCustomer();
        $mapping = new AccountingCustomerMapping();
        $mapping->customer = self::$customer;
        $mapping->integration_id = IntegrationType::NetSuite->value;
        $mapping->accounting_id = '1234';
        $mapping->source = AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        $customerCount = Customer::count();

        $loader = $this->getLoader();

        $input = new AccountingCustomer(
            integration: IntegrationType::NetSuite,
            accountingId: '2191',
            values: [
                'metadata' => [
                    'subsidiary' => 'Honeycomb Holdings Inc.',
                ],
                'type' => 'company',
                'number' => '1660',
                'name' => '1660',
                'active' => true,
                'phone' => null,
                'tax_id' => null,
                'payment_terms' => null,
                'notes' => '',
                'bill_to_parent' => false,
            ],
            emails: null,
            contacts: [],
            parentCustomer: new AccountingCustomer(
                integration: IntegrationType::NetSuite,
                accountingId: '2190',
                values: [
                    'metadata' => [
                        'subsidiary' => 'Honeycomb Holdings Inc.',
                        'single_select_test' => '',
                        'payment_commitment_date' => '',
                    ],
                    'type' => 'company',
                    'number' => '1661',
                    'name' => '1661',
                    'active' => true,
                    'phone' => null,
                    'tax_id' => null,
                    'payment_terms' => 'Net 30',
                    'notes' => '',
                    'autopay' => false,
                    'bill_to_parent' => false,
                ],
                contacts: [],
                // this one already created, so should not be updated
                parentCustomer: new AccountingCustomer(
                    integration: IntegrationType::NetSuite,
                    accountingId: '1234',
                    values: [
                        'type' => 'company',
                        'number' => '1662',
                        'name' => '1662',
                        'active' => true,
                        'phone' => null,
                        'tax_id' => null,
                        'payment_terms' => 'Net 30',
                        'notes' => '',
                        'autopay' => false,
                        'bill_to_parent' => false,
                    ],
                    contacts: [],
                ),
            ),
            deleted: false,
        );
        $loader->load($input);

        $this->assertEquals($customerCount + 2, Customer::count());

        /** @var Customer[] $initialCustomer */
        $initialCustomer = Customer::where('name', '1660')->execute();
        $this->assertCount(1, $initialCustomer);
        $parent1 = $initialCustomer[0]->parentCustomer();
        $this->assertEquals('1661', $parent1?->name);
        $parent2 = $parent1?->parentCustomer();
        $this->assertEquals(self::$customer->name, $parent2?->name);
    }

    public function testLoadParentCustomerInsufficientData(): void
    {
        $loader = $this->getLoader();

        $input = new AccountingCustomer(
            integration: IntegrationType::NetSuite,
            accountingId: '2189',
            values: [
                'metadata' => [
                    'subsidiary' => 'Honeycomb Holdings Inc.',
                ],
                'type' => 'company',
                'number' => '1662',
                'name' => '1662',
                'active' => true,
                'phone' => null,
                'tax_id' => null,
                'payment_terms' => null,
                'notes' => '',
                'bill_to_parent' => false,
            ],
            emails: null,
            contacts: [],
            parentCustomer: new AccountingCustomer(
                integration: IntegrationType::NetSuite,
                accountingId: '2188',
            ),
        );
        $loader->load($input);

        /** @var Customer[] $initialCustomer */
        $initialCustomer = Customer::where('name', '1662')->execute();
        $this->assertCount(1, $initialCustomer);
        $this->assertNull($initialCustomer[0]->parentCustomer());
    }

    public function testUpdateMappingInfo(): void
    {
        self::hasCustomer();
        $loader = $this->getLoader();

        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::NetSuite,
            accountingId: '654237',
            values: [
                'number' => self::$customer->number,
            ],
        );
        $this->assertNull(AccountingCustomerMapping::find(self::$customer->id));
        $loader->load($accountingCustomer);
        $mapping = AccountingCustomerMapping::findOrFail(self::$customer->id)->toArray();
        unset($mapping['created_at']);
        unset($mapping['updated_at']);
        $this->assertEquals([
            'customer_id' => self::$customer->id,
            'accounting_id' => '654237',
            'source' => 'accounting_system',
            'integration_name' => 'NetSuite',
        ], $mapping);
    }


    public function testLoadMetadata(): void
    {
        self::hasCustomer();
        $loader = $this->getLoader();
        self::$customer->metadata = (object) [
            'key1' => 'value1',
            'key2' => 'value1',
            'key3' => 'value1',
            'key4' => 'value1',
            'key5' => 'value1',
            'key6' => 'value1',
            'key7' => 'value1',
            'key8' => 'value1',
            'key9' => 'value1',
            'key10' => 'value1',
        ];
        self::$customer->saveOrFail();

        $record = $this->makeRecord();
        try {
            $loader->load($record);
            $this->fail('LoadException should not be thrown');
        } catch (LoadException $e) {
            $this->assertEquals('Could not update customer: There can only be up to 10 metadata values. 11 values were provided.', $e->getMessage());
        }

        self::hasCustomer();
        $record = $this->makeRecord();
        self::$customer->metadata = (object) [
            'key1' => 'value1',
            'key2' => 'value1',
            'key3' => 'value1',
            'key4' => 'value1',
            'key5' => 'value1',
            'key6' => '',
            'key7' => 'value1',
            'key8' => 'value1',
            'key9' => 'value1',
            'key10' => 'value1',
        ];
        self::$customer->saveOrFail();
        $loader->load($record);
        $customer = Customer::findOrFail(self::$customer->id());
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value1',
            'key3' => 'value1',
            'key4' => 'value1',
            'key5' => 'value1',
            'key7' => 'value1',
            'key8' => 'value1',
            'key9' => 'value1',
            'key10' => 'value1',
            'new_key1' => 'value1',
        ], (array) $customer->metadata);
    }

    private function makeRecord(): AccountingCustomer
    {
        return new AccountingCustomer(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: '9649849',
            values: [
                'number' => self::$customer->number,
                'metadata' => [
                    'new_key1' => 'value1',
                    'new_key2' => '',
                ],
            ],
        );
    }
}
