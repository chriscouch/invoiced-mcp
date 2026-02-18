<?php

namespace App\Tests\Metadata;

use App\Core\Utils\Enums\ObjectType;
use App\Metadata\Libs\CustomFieldRepository;
use App\Metadata\Models\CustomField;
use App\Tests\AppTestCase;

class CustomFieldRepositoryTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        $customField1 = new CustomField();
        $customField1->id = 'test1';
        $customField1->object = ObjectType::Customer->typeName();
        $customField1->type = CustomField::FIELD_TYPE_STRING;
        $customField1->name = 'Test 1';
        $customField1->external = false;
        $customField1->saveOrFail();

        $customField2 = new CustomField();
        $customField2->id = 'test2';
        $customField2->object = ObjectType::Invoice->typeName();
        $customField2->type = CustomField::FIELD_TYPE_BOOLEAN;
        $customField2->name = 'Test 2';
        $customField2->external = true;
        $customField2->saveOrFail();

        $customField3 = new CustomField();
        $customField3->id = 'test3';
        $customField3->type = CustomField::FIELD_TYPE_MONEY;
        $customField3->name = 'Test 3';
        $customField3->object = ObjectType::Invoice->typeName();
        $customField3->external = false;
        $customField3->saveOrFail();

        $customField4 = new CustomField();
        $customField4->id = 'test4';
        $customField4->type = CustomField::FIELD_TYPE_DATE;
        $customField4->name = 'Test 4';
        $customField4->object = ObjectType::Customer->typeName();
        $customField4->external = false;
        $customField4->saveOrFail();
    }

    public function testGetFieldsForObject(): void
    {
        $repository = CustomFieldRepository::get(self::$company);

        $ids = [];
        $fields = $repository->getFieldsForObject('invoice');
        foreach ($fields as $customField) {
            $ids[] = $customField->id;
        }
        $this->assertEquals(['test2', 'test3'], $ids);

        $ids = [];
        $fields = $repository->getFieldsForObject('customer');
        foreach ($fields as $customField) {
            $ids[] = $customField->id;
        }
        $this->assertEquals(['test1', 'test4'], $ids);

        $ids = [];
        $fields = $repository->getFieldsForObject('line_item');
        foreach ($fields as $customField) {
            $ids[] = $customField->id;
        }
        $this->assertEquals([], $ids);

        $ids = [];
        $fields = $repository->getFieldsForObject('invoice', true);
        foreach ($fields as $customField) {
            $ids[] = $customField->id;
        }
        $this->assertEquals(['test2'], $ids);

        $ids = [];
        $fields = $repository->getFieldsForObject('line_item', false);
        foreach ($fields as $customField) {
            $ids[] = $customField->id;
        }
        $this->assertEquals([], $ids);
    }

    public function testGetCustomField(): void
    {
        $repository = CustomFieldRepository::get(self::$company);

        $this->assertNull($repository->getCustomField('invoice', 'does-not-exist'));
        $this->assertNull($repository->getCustomField('invoice', 'test4'));
        $this->assertNull($repository->getCustomField('line_item', 'blah'));

        /** @var CustomField $field */
        $field = $repository->getCustomField('customer', 'test1');
        $this->assertInstanceOf(CustomField::class, $field);
        $this->assertEquals('test1', $field->id);
        /** @var CustomField $field */
        $field = $repository->getCustomField('invoice', 'test3');
        $this->assertInstanceOf(CustomField::class, $field);
        $this->assertEquals('test3', $field->id);

        $this->assertNull($repository->getCustomField('sale', 'does-not-exist'));
        $this->assertNull($repository->getCustomField('sale', 'test4'));
        /** @var CustomField $field */
        $field = $repository->getCustomField('sale', 'test3');
        $this->assertInstanceOf(CustomField::class, $field);
        $this->assertEquals('test3', $field->id);
    }
}
