<?php

namespace App\Tests\Integrations\AccountingSync\Models;

use App\Integrations\AccountingSync\Enums\SyncDirection;
use App\Integrations\AccountingSync\Enums\TransformFieldType;
use App\Integrations\AccountingSync\Models\AccountingSyncFieldMapping;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;

class AccountingSyncFieldMappingTest extends AppTestCase
{
    private static AccountingSyncFieldMapping $mapping;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$mapping = new AccountingSyncFieldMapping();
        self::$mapping->integration = IntegrationType::BusinessCentral;
        self::$mapping->object_type = 'sales_invoice';
        self::$mapping->source_field = 'po_number';
        self::$mapping->destination_field = 'purchase_order';
        self::$mapping->data_type = TransformFieldType::String;
        self::$mapping->direction = SyncDirection::Read;
        self::$mapping->enabled = true;
        $this->assertTrue(self::$mapping->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$mapping->id,
            'data_type' => 'String',
            'destination_field' => 'purchase_order',
            'enabled' => true,
            'integration' => 'business_central',
            'object_type' => 'sales_invoice',
            'source_field' => 'po_number',
            'direction' => 'Read',
            'value' => null,
        ];

        $this->assertEquals($expected, self::$mapping->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$mapping->source_field = 'test2';
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
