<?php

namespace App\Tests\Integrations\AccountingSync\Models;

use App\Integrations\AccountingSync\Models\AccountingSyncReadFilter;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;

class AccountingSyncReadFilterTest extends AppTestCase
{
    private static AccountingSyncReadFilter $filter;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreateInvalidFormula(): void
    {
        $filter = new AccountingSyncReadFilter();
        $filter->integration = IntegrationType::BusinessCentral;
        $filter->object_type = 'sales_invoice';
        $filter->formula = 'record...invalid formula';
        $filter->enabled = true;
        $this->assertFalse($filter->save());
    }

    public function testCreate(): void
    {
        self::$filter = new AccountingSyncReadFilter();
        self::$filter->integration = IntegrationType::BusinessCentral;
        self::$filter->object_type = 'sales_invoice';
        self::$filter->formula = 'record.type == "Sales Invoice"';
        self::$filter->enabled = true;
        $this->assertTrue(self::$filter->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$filter->id,
            'formula' => 'record.type == "Sales Invoice"',
            'enabled' => true,
            'integration' => 'business_central',
            'object_type' => 'sales_invoice',
        ];

        $this->assertEquals($expected, self::$filter->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$filter->enabled = false;
        $this->assertTrue(self::$filter->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$filter->delete());
    }
}
