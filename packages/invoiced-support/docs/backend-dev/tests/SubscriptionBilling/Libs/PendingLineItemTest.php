<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Item;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\Core\Utils\Enums\ObjectType;
use App\SalesTax\Models\TaxRate;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\Tests\AppTestCase;
use stdClass;

class PendingLineItemTest extends AppTestCase
{
    private static PendingLineItem $line;
    private static PendingLineItem $line2;
    private static TaxRate $taxInclusive;
    private static Coupon $coupon2;
    private static Item $item2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasItem();
        self::hasCoupon();
        self::hasTaxRate();

        self::$taxInclusive = new TaxRate();
        self::$taxInclusive->id = 'inclusive';
        self::$taxInclusive->name = 'inclusive';
        self::$taxInclusive->value = 20;
        self::$taxInclusive->inclusive = true;
        self::$taxInclusive->saveOrFail();

        self::$coupon2 = new Coupon();
        self::$coupon2->id = 'coupon2';
        self::$coupon2->name = 'Coupon';
        self::$coupon2->is_percent = false;
        self::$coupon2->value = 10;
        self::$coupon2->saveOrFail();

        self::$item2 = new Item();
        self::$item2->id = 'noprice';
        self::$item2->name = 'No Price';
        self::$item2->saveOrFail();
    }

    public function testParent(): void
    {
        $line = new PendingLineItem();

        $customer = new Customer(['id' => 100]);
        $line->setParent($customer);
        $parent = $line->parent();
        $this->assertInstanceOf(Customer::class, $parent);
        $this->assertEquals(100, $parent->id());
    }

    public function testEventAssociations(): void
    {
        $line = new PendingLineItem();
        $line->customer_id = 10;

        $expected = [
            ['customer', 10],
        ];

        $this->assertEquals($expected, $line->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $line = new PendingLineItem();
        $line->customer_id = (int) self::$customer->id();

        $this->assertEquals(array_merge($line->toArray(), [
            'customer' => self::$customer->toArray(),
        ]), $line->getEventObject());
    }

    public function testCannotCreate(): void
    {
        $line = new PendingLineItem();
        $line->type = 'product';
        $line->name = 'Test Item';
        $line->unit_cost = 10;
        $line->quantity = 1;
        $this->assertFalse($line->save());
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$line = new PendingLineItem();
        self::$line->setParent(self::$customer);
        self::$line->type = 'product';
        self::$line->name = 'Test Item';
        self::$line->unit_cost = 10;
        self::$line->quantity = 1;
        $this->assertTrue(self::$line->save());

        $this->assertEquals(self::$company->id(), self::$line->tenant_id);
        $this->assertEquals(10, self::$line->amount);
    }

    public function testCannotCreateInvalidCatalogItem(): void
    {
        $line = new PendingLineItem();
        $line->setParent(self::$customer);
        $line->type = 'product';
        $line->name = 'Test Item';
        $line->catalog_item = 'does not exist';
        $line->unit_cost = 10;
        $line->quantity = 1;
        $this->assertFalse($line->save());

        $this->assertEquals('No such item: does not exist', $line->getErrors()[0]['message']);
    }

    public function testCreateFromCatalogItem(): void
    {
        self::$line2 = new PendingLineItem();
        self::$line2->setParent(self::$customer);
        self::$line2->catalog_item = 'test-item';
        $this->assertTrue(self::$line2->save());

        $this->assertEquals(1, self::$line2->quantity);
        $this->assertEquals(1000, self::$line2->unit_cost);
        $this->assertEquals('Test Item', self::$line2->name);
        $this->assertEquals('Description', self::$line2->description);
        $this->assertEquals(1000, self::$line2->amount);

        $catalogItem = new Item();
        $catalogItem->id = 'test-item-2';
        $catalogItem->name = 'Test Item 2';
        $catalogItem->taxable = false;
        $catalogItem->discountable = false;
        $catalogItem->unit_cost = 99;
        $catalogItem->saveOrFail();

        $line3 = new PendingLineItem();
        $line3->setParent(self::$customer);
        $line3->catalog_item = $catalogItem->id;
        $line3->unit_cost = 100;
        $line3->quantity = 10;
        $this->assertTrue($line3->save());
        $this->assertEquals(100, $line3->unit_cost);
        $this->assertEquals(1000, $line3->amount);
        $this->assertFalse($line3->taxable);
        $this->assertFalse($line3->discountable);

        $catalogItemTaxed = new Item();
        $catalogItemTaxed->id = 'test-item-3';
        $catalogItemTaxed->name = 'Test Item 3';
        $catalogItemTaxed->taxes = [self::$taxRate->id];
        $catalogItemTaxed->unit_cost = 99;
        $catalogItemTaxed->saveOrFail();

        $line3 = new PendingLineItem();
        $line3->setParent(self::$customer);
        $line3->catalog_item = $catalogItemTaxed->id;
        $line3->unit_cost = 100;
        $line3->quantity = 10;
        $this->assertTrue($line3->save());
        $this->assertEquals(100, $line3->unit_cost);
        $this->assertEquals(1000, $line3->amount);
        $taxes = $line3->taxes();
        $this->assertCount(1, $taxes);
        $this->assertEquals(50, $taxes[0]['amount']);
        $this->assertEquals(self::$taxRate->id, $taxes[0]['tax_rate']['id']);

        $line4 = new PendingLineItem();
        $line4->setParent(self::$customer);
        $line4->catalog_item = self::$item2->id;
        $line4->unit_cost = 500;
        $line4->quantity = 4;
        $this->assertTrue($line4->save());

        $catalogItemTaxedInclusive = new Item();
        $catalogItemTaxedInclusive->id = 'test-item-4';
        $catalogItemTaxedInclusive->name = 'Test Item 4';
        $catalogItemTaxedInclusive->taxes = [self::$taxInclusive->id];
        $catalogItemTaxedInclusive->unit_cost = 100;
        $catalogItemTaxedInclusive->saveOrFail();

        $line5 = new PendingLineItem();
        $line5->setParent(self::$customer);
        $line5->catalog_item = $catalogItemTaxedInclusive->id;
        $line5->unit_cost = 100;
        $line5->quantity = 10;
        $this->assertTrue($line5->save());
        $this->assertEquals(100, $line5->unit_cost);
        $this->assertEquals(833.33, $line5->amount);
        $taxes = $line5->taxes();
        $this->assertCount(1, $taxes);
        $this->assertEquals(166.67, $taxes[0]['amount']);
        $this->assertEquals(self::$taxInclusive->id, $taxes[0]['tax_rate']['id']);
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        self::getService('test.event_spool')->flush(); // write out events

        $n = Event::where('type_id', EventType::LineItemCreated->toInteger())
            ->where('object_type_id', ObjectType::LineItem->value)
            ->where('object_id', self::$line)
            ->count();
        $this->assertEquals(1, $n);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $lines = PendingLineItem::all();

        $this->assertCount(6, $lines);
        $this->assertEquals(self::$line->id(), $lines[0]->id());
        $this->assertEquals(self::$line2->id(), $lines[1]->id());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$line->id(),
            'object' => 'line_item',
            'customer' => self::$customer->id(),
            'catalog_item' => null,
            'type' => 'product',
            'name' => 'Test Item',
            'description' => null,
            'discountable' => true,
            'discounts' => [],
            'taxable' => true,
            'taxes' => [],
            'quantity' => 1,
            'unit_cost' => 10,
            'amount' => 10,
            'metadata' => new stdClass(),
            'created_at' => self::$line->created_at,
            'updated_at' => self::$line->updated_at,
        ];

        $this->assertEquals($expected, self::$line->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$line->unit_cost = 20;
        $this->assertTrue(self::$line->save());
        $this->assertEquals(20, self::$line->amount);
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        self::getService('test.event_spool')->flush(); // write out events

        $n = Event::where('type_id', EventType::LineItemUpdated->toInteger())
            ->where('object_type_id', ObjectType::LineItem->value)
            ->where('object_id', self::$line)
            ->count();
        $this->assertEquals(1, $n);
    }

    /**
     * @depends testEdit
     */
    public function testAddRate(): void
    {
        self::$line->discounts = [[
            'amount' => 5,
            'coupon' => 'coupon', ]];
        $this->assertTrue(self::$line->save());

        $expected = [
            [
                'amount' => 5,
                'coupon' => self::$coupon->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
        ];

        $discounts = self::$line->discounts();
        unset($discounts[0]['id']);
        unset($discounts[0]['object']);
        unset($discounts[0]['updated_at']);
        $this->assertEquals($expected, $discounts);

        $this->assertEquals([], self::$line->taxes());
    }

    /**
     * @depends testAddRate
     */
    public function testEditRate(): void
    {
        $discounts = self::$line->discounts();
        $expectedId = $discounts[0]['id'];
        $discounts = [
            [
                'amount' => 10,
                'coupon' => 'coupon2',
            ],
            $discounts[0],
        ];

        self::$line->discounts = $discounts;
        $this->assertTrue(self::$line->save());

        $expected = [
            [
                'amount' => 10,
                'coupon' => self::$coupon2->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
            [
                'id' => $expectedId,
                'amount' => 5,
                'coupon' => self::$coupon->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
        ];

        $discounts = self::$line->discounts();
        unset($discounts[0]['id']);
        unset($discounts[0]['object']);
        unset($discounts[0]['updated_at']);
        unset($discounts[1]['object']);
        unset($discounts[1]['updated_at']);
        $this->assertEquals($expected, $discounts);

        $this->assertEquals([], self::$line->taxes());
    }

    /**
     * @depends testEditRate
     */
    public function testDeleteRate(): void
    {
        $discounts = self::$line->discounts();
        unset($discounts[0]);

        self::$line->discounts = $discounts;
        $this->assertTrue(self::$line->save());

        $expected = [
            [
                'amount' => 5,
                'coupon' => self::$coupon->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
        ];

        $discounts = self::$line->discounts();
        unset($discounts[0]['id']);
        unset($discounts[0]['object']);
        unset($discounts[0]['updated_at']);
        $this->assertEquals($expected, $discounts);

        $this->assertEquals([], self::$line->taxes());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        EventSpool::enable();

        $this->assertTrue(self::$line->delete());
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        self::getService('test.event_spool')->flush(); // write out events

        $n = Event::where('type_id', EventType::LineItemDeleted->toInteger())
            ->where('object_type_id', ObjectType::LineItem->value)
            ->where('object_id', self::$line)
            ->count();
        $this->assertEquals(1, $n);
    }
}
