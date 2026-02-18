<?php

namespace App\Tests\Exports;

use App\Exports\Models\Export;
use App\Tests\AppTestCase;

class ExportTest extends AppTestCase
{
    private static Export $export;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$export = new Export();
        self::$export->name = 'Test';
        self::$export->status = Export::PENDING;
        self::$export->type = 'invoice';
        $this->assertTrue(self::$export->save());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$export->message = 'update';
        $this->assertTrue(self::$export->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$export->delete());
    }
}
