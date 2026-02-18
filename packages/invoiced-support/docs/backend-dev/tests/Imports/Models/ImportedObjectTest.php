<?php

namespace App\Tests\Imports\Models;

use App\Core\Orm\Exception\DriverException;
use App\Core\Utils\Enums\ObjectType;
use App\Imports\Models\Import;
use App\Imports\Models\ImportedObject;
use App\Tests\AppTestCase;

class ImportedObjectTest extends AppTestCase
{
    private static ?int $importId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    /**
     * Creates and saves an import object.
     */
    public function createImport(): int
    {
        $import = new Import();
        $import->name = 'TEST IMPORT';
        $import->type = 'TEST IMPORT';
        $import->status = Import::SUCCEEDED;
        $import->saveOrFail();

        return $import->id;
    }

    public function testCreate(): void
    {
        // Test case 1.
        // Test creating object throws error when import doesn't exist
        $object1 = new ImportedObject([
            'import' => 99999,
            'object' => ObjectType::Customer->value,
            'object_id' => 1,
        ]);

        $error = null;
        try {
            $object1->save();
        } catch (\Exception $e) {
            $error = $e;
        }
        $this->assertInstanceOf(DriverException::class, $error);

        // Test case 2.
        // Test creating object fails if missing properties
        $object2 = new ImportedObject([
            'import' => 99999,
            'object' => ObjectType::Customer->value,
        ]);

        $this->assertFalse($object2->save());

        // Test case 3.
        // Test successful save when properties and import exist
        self::$importId = $this->createImport();
        $object3 = new ImportedObject([
            'import' => self::$importId,
            'object' => ObjectType::Customer->value,
            'object_id' => 1,
        ]);

        $this->assertTrue($object3->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $result = ImportedObject::where('import', self::$importId)
            ->where('object', ObjectType::Customer->value)
            ->where('object_id', 1)
            ->oneOrNull();

        $this->assertInstanceOf(ImportedObject::class, $result);
    }

    /**
     * @depends testQuery
     */
    public function testUpdate(): void
    {
        // Retrieve object from DB and update it
        $importedObject = ImportedObject::where('import', self::$importId)
            ->where('object', ObjectType::Customer->value)
            ->where('object_id', '1')
            ->one();
        $importedObject->object_id = '2';
        $this->assertTrue($importedObject->save());

        // Retrieve object from DB check that its updated
        $importedObject = ImportedObject::where('import', self::$importId)
            ->where('object', ObjectType::Customer->value)
            ->where('object_id', '2')
            ->one();
        $this->assertEquals('2', $importedObject->object_id);
    }

    /**
     * @depends testUpdate
     */
    public function testDelete(): void
    {
        // Retrieve object from DB and delete it
        $importedObject = ImportedObject::where('import', self::$importId)
            ->where('object', ObjectType::Customer->value)
            ->where('object_id', 2)
            ->one();
        $this->assertTrue($importedObject->delete());

        // Attempt to find deleted object
        $importedObject = ImportedObject::where('import', self::$importId)
            ->where('object', ObjectType::Customer->value)
            ->where('object_id', 2)
            ->oneOrNull();
        $this->assertNull($importedObject);
    }
}
