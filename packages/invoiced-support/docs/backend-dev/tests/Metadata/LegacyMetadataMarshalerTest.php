<?php

namespace App\Tests\Metadata;

use App\Metadata\Libs\LegacyMetadataMarshaler;
use App\Metadata\Models\CustomField;
use App\Tests\AppTestCase;

class LegacyMetadataMarshalerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        $customField = new CustomField();
        $customField->object = 'invoice';
        $customField->id = 'test';
        $customField->name = 'Test';
        $customField->type = CustomField::FIELD_TYPE_BOOLEAN;
        $customField->saveOrFail();
    }

    public function getMarshaler(): LegacyMetadataMarshaler
    {
        return new LegacyMetadataMarshaler(self::$company);
    }

    public function testCastToStorageNoCustomField(): void
    {
        $marshaler = $this->getMarshaler();

        $this->assertEquals('blah', $marshaler->castToStorage('invoice', 'does-not-exist', 'blah'));
        $this->assertEquals('{"test":true}', $marshaler->castToStorage('invoice', 'does-not-exist', ['test' => true]));
        $this->assertEquals('{"test":true}', $marshaler->castToStorage('invoice', 'does-not-exist', (object) ['test' => true]));
    }

    public function testCastToStorageCustomField(): void
    {
        $marshaler = $this->getMarshaler();

        $this->assertEquals('1', $marshaler->castToStorage('invoice', 'test', true));
    }

    public function testCastToStorageString(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_STRING;

        $this->assertEquals('blah', $marshaler->to_storage_string('blah', $customField));
        $this->assertEquals('1', $marshaler->to_storage_string(1, $customField));
        $this->assertEquals('[1,2,3,4]', $marshaler->to_storage_string([1, 2, 3, 4], $customField));
        $this->assertEquals('{"test":true}', $marshaler->to_storage_string((object) ['test' => true], $customField));
    }

    public function testCastToStorageEnum(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_ENUM;

        $this->assertEquals('blah', $marshaler->to_storage_enum('blah', $customField));
        $this->assertEquals('blah2', $marshaler->to_storage_enum('blah2', $customField));
        $this->assertEquals('1', $marshaler->to_storage_enum(1, $customField));
    }

    public function testCastToStorageBoolean(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_STRING;

        $this->assertEquals('0', $marshaler->to_storage_boolean(false, $customField));
        $this->assertEquals('1', $marshaler->to_storage_boolean(true, $customField));
        $this->assertEquals('0', $marshaler->to_storage_boolean('false', $customField));
        $this->assertEquals('1', $marshaler->to_storage_boolean('true', $customField));
        $this->assertEquals('0', $marshaler->to_storage_boolean('0', $customField));
        $this->assertEquals('1', $marshaler->to_storage_boolean('1', $customField));
    }

    public function testCastToStorageDouble(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_DOUBLE;

        $this->assertEquals('1', $marshaler->to_storage_double(1, $customField));
        $this->assertEquals('18.384', $marshaler->to_storage_double(18.384, $customField));
    }

    public function testCastToStorageDate(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_DATE;

        $this->assertEquals('1123413241234', $marshaler->to_storage_date('1123413241234', $customField));
    }

    public function testCastToStorageMoney(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_MONEY;

        $this->assertEquals('usd,100', $marshaler->to_storage_money('usd,100', $customField));
    }

    public function testCastFromStorageNoCustomField(): void
    {
        $marshaler = $this->getMarshaler();

        $this->assertEquals('blah', $marshaler->castFromStorage('invoice', 'does-not-exist', 'blah'));
    }

    public function testCastFromStorageCustomField(): void
    {
        $marshaler = $this->getMarshaler();

        $this->assertTrue($marshaler->castFromStorage('invoice', 'test', '1'));
    }

    public function testCastFromStorageString(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_STRING;

        $this->assertEquals('blah', $marshaler->from_storage_string('blah', $customField));
        $this->assertEquals('1', $marshaler->from_storage_string('1', $customField));
    }

    public function testCastFromStorageEnum(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_ENUM;

        $this->assertEquals('blah', $marshaler->from_storage_enum('blah', $customField));
        $this->assertEquals('blah2', $marshaler->from_storage_enum('blah2', $customField));
        $this->assertEquals('1', $marshaler->from_storage_enum('1', $customField));
    }

    public function testCastFromStorageBoolean(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_STRING;

        $this->assertFalse($marshaler->from_storage_boolean('0', $customField));
        $this->assertTrue($marshaler->from_storage_boolean('1', $customField));
    }

    public function testCastFromStorageDouble(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_DOUBLE;

        $this->assertEquals(1, $marshaler->from_storage_double('1', $customField));
        $this->assertEquals(18.384, $marshaler->from_storage_double('18.384', $customField));
    }

    public function testCastFromStorageDate(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_DATE;

        $this->assertEquals(1123413241234, $marshaler->from_storage_date('1123413241234', $customField));
    }

    public function testCastFromStorageMoney(): void
    {
        $marshaler = $this->getMarshaler();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_MONEY;

        $this->assertEquals('usd,100', $marshaler->from_storage_money('usd,100', $customField));
    }
}
