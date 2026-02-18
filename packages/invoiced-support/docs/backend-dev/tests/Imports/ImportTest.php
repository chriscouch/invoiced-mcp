<?php

namespace App\Tests\Imports;

use App\Imports\Models\Import;
use App\Tests\AppTestCase;

class ImportTest extends AppTestCase
{
    private static Import $import;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$import = new Import();
        self::$import->name = 'Test';
        self::$import->status = Import::PENDING;
        self::$import->type = 'invoice';
        self::$import->source_file = '1234';
        $this->assertTrue(self::$import->save());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$import->num_failed = 10;
        $this->assertTrue(self::$import->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$import->delete());
    }
}
