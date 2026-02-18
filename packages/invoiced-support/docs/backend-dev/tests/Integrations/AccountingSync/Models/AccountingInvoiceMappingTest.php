<?php

namespace App\Tests\Integrations\AccountingSync\Models;

use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;

class AccountingInvoiceMappingTest extends AppTestCase
{
    private static AccountingInvoiceMapping $mapping;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    public function testCreate(): void
    {
        self::$mapping = new AccountingInvoiceMapping();
        self::$mapping->invoice = self::$invoice;
        self::$mapping->integration_id = IntegrationType::Intacct->value;
        self::$mapping->accounting_id = '1234';
        self::$mapping->source = AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM;
        $this->assertTrue(self::$mapping->save());

        $this->assertEquals(self::$company->id(), self::$mapping->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $mappings = AccountingInvoiceMapping::all();

        $this->assertCount(1, $mappings);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'invoice_id' => self::$invoice->id(),
            'accounting_id' => '1234',
            'source' => 'accounting_system',
            'integration_name' => 'Intacct',
            'created_at' => self::$mapping->created_at,
            'updated_at' => self::$mapping->updated_at,
        ];

        $this->assertEquals($expected, self::$mapping->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$mapping->integration_id = IntegrationType::NetSuite->value;
        self::$mapping->accounting_id = '1235';
        $this->assertTrue(self::$mapping->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$mapping->delete());
    }
}
