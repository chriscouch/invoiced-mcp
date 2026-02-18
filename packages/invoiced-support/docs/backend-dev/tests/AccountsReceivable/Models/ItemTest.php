<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\GlAccount;
use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Models\PricingObject;
use App\Tests\AppTestCase;
use stdClass;

class ItemTest extends AppTestCase
{
    private static Item $item2;
    private static Item $item3;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testLineItem(): void
    {
        $item = new Item(['id' => -1]);
        $item->id = 'test';
        $item->internal_id = 1;
        $item->type = 'product';
        $item->name = 'Test Item';
        $item->unit_cost = 10;

        $expected = [
            'type' => 'product',
            'name' => 'Test Item',
            'description' => null,
            'discountable' => true,
            'taxable' => true,
            'taxes' => [],
            'unit_cost' => 10,
            'catalog_item' => 'test',
            'catalog_item_id' => 1,
        ];

        $this->assertEquals($expected, $item->lineItem());
    }

    public function testCreateInvalidID(): void
    {
        $item = new Item();
        $item->type = 'product';
        $item->name = 'Test Item';
        $item->id = 'ABC*#$&)#&%)*#)(%*';
        $item->unit_cost = 10;

        $this->assertFalse($item->save());
    }

    public function testCreate(): void
    {
        self::$item = new Item();
        $this->assertTrue(self::$item->create([
            'type' => 'product',
            'id' => 'test-item',
            'name' => 'Test Item',
            'unit_cost' => 10,
        ]));

        $this->assertEquals(self::$company->id(), self::$item->tenant_id);
        $this->assertEquals('usd', self::$item->currency);

        self::$item2 = new Item();
        $this->assertTrue(self::$item2->create([
            'type' => 'product',
            'id' => 'test-item-2',
            'name' => 'Test Item 2',
            'currency' => 'eur',
            'unit_cost' => 10,
        ]));
        $this->assertEquals('usd', self::$item->currency);

        self::$item3 = new Item();
        self::$item3->id = 'test-item-3';
        self::$item3->name = 'Test Item 3';
        $this->assertTrue(self::$item3->save());
        $this->assertNull(self::$item3->currency);
        $this->assertEquals(0, self::$item3->unit_cost);
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        $item = new Item();
        $errors = $item->getErrors();

        $item->name = 'Test Item';
        $item->id = 'test-item';
        $item->unit_cost = 10;
        $this->assertFalse($item->save());

        $this->assertCount(1, $errors);
        $this->assertEquals('An item already exists with ID: test-item', $errors->all()[0]);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $items = Item::all();

        $this->assertCount(3, $items);
        $this->assertEquals(self::$item->id(), $items[0]->id());
        $this->assertEquals(self::$item2->id(), $items[1]->id());
        $this->assertEquals(self::$item3->id(), $items[2]->id());
    }

    /**
     * @depends testCreate
     */
    public function testGetCurrent(): void
    {
        $this->assertEquals(self::$item, Item::getCurrent('test-item'));
        $this->assertNull(Item::getCurrent('does-not-exist'));
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => 'test-item',
            'object' => 'item',
            'type' => 'product',
            'name' => 'Test Item',
            'description' => null,
            'gl_account' => null,
            'discountable' => true,
            'taxable' => true,
            'taxes' => [],
            'currency' => 'usd',
            'unit_cost' => 10,
            'avalara_tax_code' => null,
            'avalara_location_code' => null,
            'metadata' => new stdClass(),
            'created_at' => self::$item->created_at,
            'updated_at' => self::$item->updated_at,
        ];

        $this->assertEquals($expected, self::$item->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$item->name = 'Test';
        $this->assertTrue(self::$item->save());
    }

    /**
     * @depends testCreate
     */
    public function testCannotChangeID(): void
    {
        self::$item2->id = 'test-item';
        $this->assertTrue(self::$item2->save());
        $this->assertNotEquals('test-item', self::$item2->id);
    }

    /**
     * @depends testCreate
     */
    public function testCannotChangePrice(): void
    {
        self::$item2->unit_cost = 100000;
        $this->assertTrue(self::$item2->save());
        $this->assertNotEquals(100000, self::$item2->unit_cost);
    }

    /**
     * @depends testEdit
     */
    public function testArchivedNotHidden(): void
    {
        $this->assertTrue(self::$item->archive());

        $items = Item::all();
        $this->assertCount(3, $items);

        $this->assertEquals(1, Item::where('archived', true)->count());

        $this->assertEquals(2, Item::where('archived', false)->count());
    }

    /**
     * @depends testCreate
     */
    public function testMetadata(): void
    {
        $metadata = self::$item->metadata;
        $metadata->test = true;
        self::$item->metadata = $metadata;
        $this->assertTrue(self::$item->save());
        $this->assertEquals((object) ['test' => true], self::$item->metadata);

        self::$item->metadata = (object) ['internal.id' => '12345'];
        $this->assertTrue(self::$item->save());
        $this->assertEquals((object) ['internal.id' => '12345'], self::$item->metadata);

        self::$item->metadata = (object) ['array' => [], 'object' => new stdClass()];
        $this->assertTrue(self::$item->save());
        $this->assertEquals((object) ['array' => [], 'object' => new stdClass()], self::$item->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata(): void
    {
        self::$item->metadata = (object) [str_pad('', 41) => 'fail'];
        $this->assertFalse(self::$item->save());

        self::$item->metadata = (object) ['fail' => str_pad('', 256)];
        $this->assertFalse(self::$item->save());

        self::$item->metadata = (object) array_fill(0, 11, 'fail');
        $this->assertFalse(self::$item->save());

        self::$item->metadata = (object) [];
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        // deleting is the same as archiving
        $this->assertTrue(self::$item->delete());
        $this->assertTrue(self::$item->persisted());
        $this->assertTrue(self::$item->archived);
    }

    /**
     * @depends testDelete
     */
    public function testCanCreateIdAfterDelete(): void
    {
        $item = new Item();
        $item->name = 'Test Item';
        $item->id = 'test-item';
        $item->unit_cost = 20;
        $this->assertTrue($item->save());
    }

    public function testArchiving(): void
    {
        $item = new Item();
        $item->id = 'archived';
        $item->name = 'Test';
        $item->unit_cost = 0;
        $this->assertTrue($item->save());

        // archive it
        $this->assertTrue($item->archive());

        // try to look it up
        $item2 = Item::find($item->internal_id);
        $this->assertNull(Item::getCurrent('archived'));

        // unarchive it
        $item->archived = false;
        $this->assertTrue($item->save());

        // archive it again
        $this->assertTrue($item->save());
    }

    /**
     * #INVD-692
     * GlAccount::code validation must equal
     * CatalogItem::gl_account validation.
     */
    public function testGlAccountValidation(): void
    {
        $item = new Item();
        $account = new GlAccount();
        // required data
        $account->name = 'test-account';
        $item->name = 'test-item';
        $item->id = 'test';

        // test less then 4 characters
        $this->_glCodeSave($item, $account, 'aaa', 'Minimum characters requirements broken');
        // test invalid chracter
        $this->_glCodeSave($item, $account, 'aaa-216326783;', 'Invalid characters requirements broken');
        // test valid
        // more then 15 characters #INVD-692
        $this->_glCodeSave($item, $account, 'aaa-216326837-435', 'CatalogItem is not saved with more 15 charectedrs in the code');
    }

    private function _glCodeSave(Item $item, GlAccount $account, string $id, string $message): void
    {
        $account->code = $id;
        $item->gl_account = $id;
        $this->assertEquals($account->save(), $item->save(), $message);
    }

    public function testCreateMissingID(): void
    {
        $item = new Item();
        $item->type = 'product';
        $item->name = 'Test Item';
        $item->unit_cost = 10;

        $this->assertTrue($item->save());
        $this->assertEquals(PricingObject::ID_LENGTH, strlen($item->id));
    }
}
