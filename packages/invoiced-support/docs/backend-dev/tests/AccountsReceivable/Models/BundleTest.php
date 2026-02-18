<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\Bundle;
use App\Tests\AppTestCase;

class BundleTest extends AppTestCase
{
    private static Bundle $bundle;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasItem();
    }

    public function testCreateMissingID(): void
    {
        $bundle = new Bundle();
        $bundle->name = 'Test';
        $bundle->items = [['catalog_item' => self::$item->id, 'quantity' => 1]];
        $this->assertFalse($bundle->save());
    }

    public function testCreateInvalidID(): void
    {
        $bundle = new Bundle();
        $bundle->name = 'Test';
        $bundle->id = '$*%)#*%#)%';
        $bundle->items = [['catalog_item' => self::$item->id, 'quantity' => 1]];
        $this->assertFalse($bundle->save());
    }

    public function testCreateInvalidItems(): void
    {
        $bundle = new Bundle();
        $bundle->id = 'test';
        $bundle->name = 'Test';
        $this->assertFalse($bundle->save());
    }

    public function testCreate(): void
    {
        self::$bundle = new Bundle();
        self::$bundle->id = 'test';
        self::$bundle->name = 'Test';
        self::$bundle->items = [['catalog_item' => self::$item->id, 'quantity' => 1]];
        $this->assertTrue(self::$bundle->save());
        $this->assertEquals(self::$company->id(), self::$bundle->tenant_id);
        $this->assertGreaterThan(0, self::$bundle->internal_id);
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        $bundle = new Bundle();
        $bundle->id = 'test';
        $bundle->name = 'Test';
        $bundle->items = [['catalog_item' => self::$item->id, 'quantity' => 1]];
        $this->assertFalse($bundle->save());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$bundle->currency = 'eur';
        $this->assertTrue(self::$bundle->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $bundles = Bundle::all();

        $this->assertCount(1, $bundles);
        $this->assertEquals(self::$bundle->id(), $bundles[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$bundle->id,
            'name' => 'Test',
            'currency' => 'usd',
            'items' => [
                [
                    'catalog_item' => self::$item->toArray(),
                    'quantity' => 1,
                ],
            ],
            'created_at' => self::$bundle->created_at,
            'updated_at' => self::$bundle->updated_at,
        ];

        $this->assertEquals($expected, self::$bundle->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testGet(): void
    {
        $this->assertEquals(self::$company->id(), self::$bundle->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$bundle->delete());
    }
}
