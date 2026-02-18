<?php

namespace App\Tests\Metadata;

use App\Metadata\Models\CustomField;
use App\Tests\AppTestCase;

class CustomFieldTest extends AppTestCase
{
    private static CustomField $customField2;
    private static CustomField $customField3;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasFile();
    }

    public function testCreateMissingID(): void
    {
        $customField = new CustomField();
        $customField->object = 'customer';
        $customField->name = 'Test field';

        $this->assertFalse($customField->save());
    }

    public function testCreateInvalidID(): void
    {
        $customField = new CustomField();
        $customField->id = 'ABC*#$&)#&%)*#)(%*';
        $customField->object = 'customer';
        $customField->name = 'Test field';

        $this->assertFalse($customField->save());
    }

    public function testCreateInvalidIDLength(): void
    {
        $customField = new CustomField();
        $customField->id = str_pad('', 41, 'f');
        $customField->object = 'customer';
        $customField->name = 'Test field';

        $this->assertFalse($customField->save());
    }

    public function testCreateMissingObject(): void
    {
        $customField = new CustomField();
        $customField->id = 'test';
        $customField->name = 'Test field';

        $this->assertFalse($customField->save());
    }

    public function testCreate(): void
    {
        self::$customField = new CustomField();
        self::$customField->id = 'test';
        self::$customField->object = 'customer';
        self::$customField->name = 'Test';
        self::$customField->choices = ['option1', 'option2', 'option1'];
        $this->assertTrue(self::$customField->save());

        $this->assertEquals(self::$company->id(), self::$customField->tenant_id);

        self::$customField2 = new CustomField();
        self::$customField2->id = 'hidden';
        self::$customField2->object = 'customer';
        self::$customField2->name = 'Hidden';
        self::$customField2->external = false;
        $this->assertTrue(self::$customField2->save());

        self::$customField3 = new CustomField();
        self::$customField3->id = 'po_number';
        self::$customField3->name = 'PO Number';
        self::$customField3->object = 'invoice';
        $this->assertTrue(self::$customField3->save());
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        // Creating a field with the same object and ID should not be permitted
        $customField = new CustomField();
        $customField->id = 'test';
        $customField->name = 'Test field';
        $customField->object = 'customer';
        $this->assertFalse($customField->save());

        // Creating an field with a different object should be permitted
        $customField = new CustomField();
        $customField->id = 'po_number';
        $customField->name = 'Test field';
        $customField->object = 'transaction';
        $this->assertTrue($customField->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $customFields = CustomField::all();

        $this->assertCount(4, $customFields);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'internal_id' => self::$customField->internal_id,
            'id' => 'test',
            'name' => 'Test',
            'choices' => ['option1', 'option2'],
            'external' => true,
            'type' => 'string',
            'object' => 'customer',
            'created_at' => self::$customField->created_at,
            'updated_at' => self::$customField->updated_at,
        ];

        $this->assertEquals($expected, self::$customField->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$customField->name = 'Test 2';
        $this->assertTrue(self::$customField->save());
    }

    /**
     * @depends testCreate
     */
    public function testCannotChangeID(): void
    {
        self::$customField->id = 'test-field';
        $this->assertTrue(self::$customField->save());
        $this->assertNotEquals('test-field', self::$customField->id);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$customField->delete());
    }
}
