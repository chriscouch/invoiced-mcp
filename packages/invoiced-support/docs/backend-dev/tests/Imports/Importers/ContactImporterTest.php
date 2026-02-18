<?php

namespace App\Tests\Imports\Importers;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\ContactRole;
use App\Imports\Importers\Spreadsheet\ContactImporter;
use App\Imports\Models\Import;
use Mockery;

class ContactImporterTest extends ImporterTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCustomer();
    }

    protected function getImporter(): ContactImporter
    {
        return self::getService('test.importer_factory')->get('contact');
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
        $this->assertEquals(7, $result->getNumCreated());
        $this->assertEquals(0, $result->getNumUpdated());

        // should create a pending line
        $lineItem = Contact::where('customer_id', self::$customer->id())->oneOrNull();
        $expected = [
            'name' => 'Holmes',
            'address1' => '1234 main st',
            'address2' => 'address2',
            'city' => 'London',
            'country' => 'GB',
            'department' => 'Department',
            'email' => 'sherlock@example.com',
            'phone' => '1234567890',
            'postal_code' => '1234',
            'primary' => true,
            'sms_enabled' => true,
            'send_new_invoices' => false,
            'state' => '4567',
            'title' => 'CEO',
            'role_id' => null,
            'customer_id' => self::$customer->id,
        ];
        $this->assertInstanceOf(Contact::class, $lineItem);
        $array = $lineItem->toArray();
        unset($array['id']);
        unset($array['object']);
        unset($array['created_at']);
        unset($array['updated_at']);
        $this->assertEquals($expected, $array);

        $this->assertEquals(7, Contact::where('customer_id', self::$customer->id())->count());

        // should update the position
        $this->assertEquals(7, $import->position);
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $contact = new Contact();
        $contact->customer = self::$customer;
        $contact->name = 'Update Test';
        $contact->saveOrFail();

        $mapping = ['account_number', 'name', 'email'];
        $lines = [
            [
                self::$customer->number,
                'Update Test',
                'test@example.com',
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals('test@example.com', $contact->refresh()->email);
    }

    public function testRunDelete(): void
    {
        $importer = $this->getImporter();

        $contact = new Contact();
        $contact->customer = self::$customer;
        $contact->name = 'Delete Test';
        $contact->saveOrFail();

        $mapping = ['account_number', 'name'];
        $lines = [
            [
                self::$customer->number,
                'Delete Test',
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'delete'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertNull(Contact::where('name', 'Delete Test')->oneOrNull());
    }

    public function testContactRole(): void
    {
        $contactRole = new ContactRole();
        $contactRole->name = 'Test Role';
        $contactRole->saveOrFail();

        $importer = $this->getImporter();
        $import = $this->getImport();

        $mapping = ['customer', 'name', 'email', 'role'];
        $lines = [['Sherlock', 'Test Contact', 'test@example.com', 'Test Role']];

        $records = $importer->build($mapping, $lines, [], $import);
        $this->assertCount(1, $records);

        $record = $records[0];
        $this->assertInstanceOf(ContactRole::class, $record['role']);
        $this->assertEquals($contactRole->id(), $record['role']->id());
    }

    protected function getLines(): array
    {
        return [
            [
                'Sherlock',
                '',
                // contact
                'Holmes',
                'sherlock@example.com',
                'CEO',
                'Department',
                '1234 main st',
                'address2',
                'London',
                '4567',
                '1234',
                'GB',
                '1234567890',
                '1',
                '1',
            ],
            [
                'Sherlock',
                '',
                // contact
                'Contact 2',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '0',
                '1',
            ],
            [
                '',
                'CUST-00001',
                // contact
                'Contact 3',
                'test@example.com',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '0',
                '0',
            ],
            [
                'Sherlock',
                '',
                // contact
                'Contact 4',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '0',
                '0',
            ],
            [
                'Sherlock',
                '',
                // contact
                'Contact 5',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '0',
                '0',
            ],
            [
                'Sherlock',
                '',
                // contact
                'Contact 6',
                '',
                'title',
                'department',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '0',
                '0',
            ],
            [
                'Sherlock',
                '',
                // contact
                'Contact 7',
                '',
                'title',
                'department',
                '5301 Southwest Parkway',
                'Suite 470',
                'Austin',
                'TX',
                '78735',
                '',
                '',
                '0',
                '0',
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'customer',
            'account_number',
            'name',
            'email',
            'title',
            'department',
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'country',
            'phone',
            'sms_enabled',
            'primary',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save,tenant]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->shouldReceive('tenant')
            ->andReturn(self::$company);
        $import->type = 'contact';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'address1' => '1234 main st',
                'address2' => 'address2',
                'city' => 'London',
                'state' => '4567',
                'postal_code' => '1234',
                'country' => 'GB',
                'phone' => '1234567890',
                'name' => 'Holmes',
                'email' => 'sherlock@example.com',
                'title' => 'CEO',
                'department' => 'Department',
                'sms_enabled' => '1',
                'primary' => '1',
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => 'Contact 2',
                'email' => '',
                'title' => '',
                'department' => '',
                'address1' => '',
                'address2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => '',
                'phone' => '',
                'sms_enabled' => false,
                'primary' => '1',
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => '',
                    'number' => 'CUST-00001',
                ],
                'name' => 'Contact 3',
                'email' => 'test@example.com',
                'title' => '',
                'department' => '',
                'address1' => '',
                'address2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => '',
                'phone' => '',
                'sms_enabled' => false,
                'primary' => false,
            ],
            // NOTE: this should not be imported because it is negative
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => 'Contact 4',
                'email' => '',
                'title' => '',
                'department' => '',
                'address1' => '',
                'address2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => '',
                'phone' => '',
                'sms_enabled' => false,
                'primary' => false,
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => 'Contact 5',
                'email' => '',
                'title' => '',
                'department' => '',
                'address1' => '',
                'address2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => '',
                'phone' => '',
                'sms_enabled' => false,
                'primary' => false,
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => 'Contact 6',
                'email' => '',
                'title' => 'title',
                'department' => 'department',
                'address1' => '',
                'address2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => '',
                'phone' => '',
                'sms_enabled' => false,
                'primary' => false,
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => 'Contact 7',
                'email' => '',
                'title' => 'title',
                'department' => 'department',
                'address1' => '5301 Southwest Parkway',
                'address2' => 'Suite 470',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78735',
                'country' => '',
                'phone' => '',
                'sms_enabled' => false,
                'primary' => false,
            ],
        ];
    }
}
