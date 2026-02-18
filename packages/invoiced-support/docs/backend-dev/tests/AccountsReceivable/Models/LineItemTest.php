<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Orm\Model;
use App\Tests\AppTestCase;
use stdClass;

class LineItemTest extends AppTestCase
{
    private static LineItem $lineItem;
    private static LineItem $lineItem2;
    private static Coupon $coupon2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInactiveCustomer();
        self::hasItem();

        // create an invoice
        self::$invoice = new Invoice();
        self::$invoice->setCustomer(self::$customer);
        self::$invoice->draft = true;
        self::$invoice->saveOrFail();

        // create coupons
        self::$coupon = new Coupon();
        self::$coupon->id = 'coupon';
        self::$coupon->name = 'Coupon';
        self::$coupon->is_percent = false;
        self::$coupon->value = 5;
        self::$coupon->saveOrFail();

        self::$coupon2 = new Coupon();
        self::$coupon2->id = 'coupon2';
        self::$coupon2->name = 'Coupon';
        self::$coupon2->is_percent = false;
        self::$coupon2->value = 10;
        self::$coupon2->saveOrFail();
    }

    public function testSetParent(): void
    {
        $lineItem = new LineItem();
        $lineItem->invoice_id = 1;
        $lineItem->estimate_id = 1;
        $lineItem->customer_id = 1;
        $lineItem->credit_note_id = 1;

        $invoice = new Invoice(['id' => 100]);
        $lineItem->setParent($invoice);
        $parent = $lineItem->parent();
        $this->assertInstanceOf(Invoice::class, $parent);
        $this->assertEquals(100, $parent->id());
        $this->assertEquals(100, $lineItem->invoice_id);
        $this->assertNull($lineItem->customer_id);
        $this->assertNull($lineItem->estimate_id);
        $this->assertNull($lineItem->credit_note_id);

        $estimate = new Estimate(['id' => 101]);
        $lineItem->setParent($estimate);
        $parent = $lineItem->parent();
        $this->assertInstanceOf(Estimate::class, $parent);
        $this->assertEquals(101, $parent->id());
        $this->assertEquals(101, $lineItem->estimate_id);
        $this->assertNull($lineItem->customer_id);
        $this->assertNull($lineItem->invoice_id);
        $this->assertNull($lineItem->credit_note_id);

        $customer = new Customer(['id' => 102]);
        $lineItem->setParent($customer);
        $parent = $lineItem->parent();
        $this->assertInstanceOf(Customer::class, $parent);
        $this->assertEquals(102, $parent->id());
        $this->assertEquals(102, $lineItem->customer_id);
        $this->assertNull($lineItem->invoice_id);
        $this->assertNull($lineItem->estimate_id);
        $this->assertNull($lineItem->credit_note_id);

        $creditNote = new CreditNote(['id' => 103]);
        $lineItem->setParent($creditNote);
        $parent = $lineItem->parent();
        $this->assertInstanceOf(CreditNote::class, $parent);
        $this->assertEquals(103, $parent->id());
        $this->assertEquals(103, $lineItem->credit_note_id);
        $this->assertNull($lineItem->customer_id);
        $this->assertNull($lineItem->estimate_id);
        $this->assertNull($lineItem->invoice_id);
    }

    public function testSanitize(): void
    {
        $test = [];
        $expected = [
            'type' => null,
            'name' => '',
            'description' => '',
            'quantity' => 1,
            'unit_cost' => 0,
            'discountable' => true,
            'discounts' => [],
            'taxable' => true,
            'taxes' => [],
        ];
        $this->assertEquals($expected, LineItem::sanitize($test));

        $test = [
            'quantity' => 10,
            'unit_cost' => 100,
        ];
        $expected = [
            'type' => null,
            'name' => '',
            'description' => '',
            'quantity' => 10,
            'unit_cost' => 100,
            'discountable' => true,
            'discounts' => [],
            'taxable' => true,
            'taxes' => [],
        ];
        $this->assertEquals($expected, LineItem::sanitize($test));

        $test = [
            'type' => 'hours',
            'quantity' => -1,
            'name' => "     \n\ntest\n\n\n    ",
            'unit_cost' => 15.58068,
        ];
        $expected = [
            'type' => 'hours',
            'name' => 'test',
            'description' => '',
            'quantity' => -1,
            'unit_cost' => 15.58068,
            'discountable' => true,
            'discounts' => [],
            'taxable' => true,
            'taxes' => [],
        ];
        $this->assertEquals($expected, LineItem::sanitize($test));

        $test = [
            'type' => 'blah',
            'quantity' => 'somequantity',
            'unit_cost' => 'somerate',
            'description' => "\n\n\n\n       content       \n\n\n\n\n\n\n\n",
        ];
        $expected = [
            'type' => 'blah',
            'name' => '',
            'description' => 'content',
            'quantity' => 0,
            'unit_cost' => 0,
            'discountable' => true,
            'discounts' => [],
            'taxable' => true,
            'taxes' => [],
        ];
        $this->assertEquals($expected, LineItem::sanitize($test));

        $test = [
            'unit_cost' => 1234,
            'catalog_item' => [],
        ];
        $expected = [
            'type' => null,
            'name' => '',
            'description' => null,
            'quantity' => 1.0,
            'unit_cost' => 1234.0,
            'catalog_item' => null,
            'discountable' => true,
            'discounts' => [],
            'taxable' => true,
            'taxes' => [],
        ];
        $this->assertEquals($expected, LineItem::sanitize($test));
    }

    public function testCalculateAmount(): void
    {
        $lineItem = [
            'unit_cost' => 15.58068,
            'quantity' => 1,
        ];

        $amount = LineItem::calculateAmount('usd', $lineItem);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(1558, $amount->amount);

        $amount = LineItem::calculateAmount('jpy', $lineItem);
        $this->assertEquals('jpy', $amount->currency);
        $this->assertEquals(16, $amount->amount);
    }

    public function testCannotCreate(): void
    {
        $lineItem = new LineItem();
        $this->assertFalse($lineItem->create([
            'type' => 'product',
            'name' => 'Test Item',
            'unit_cost' => 10,
            'quantity' => 1,
        ]));
    }

    public function testCreate(): void
    {
        self::$lineItem = $this->createLineItem(self::$invoice);
        $this->assertTrue(self::$lineItem->save());

        $this->assertEquals(self::$company->id(), self::$lineItem->tenant_id);
        $this->assertEquals(10, self::$lineItem->amount);
        $this->assertEquals(10, self::$invoice->refresh()->total);
        $this->assertEquals(self::$item->internal_id, self::$lineItem->catalog_item_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $items = LineItem::all();

        $this->assertCount(1, $items);
        $this->assertEquals(self::$lineItem->id(), $items[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$lineItem->id(),
            'object' => 'line_item',
            'catalog_item' => self::$item->id,
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
            'created_at' => self::$lineItem->created_at,
            'updated_at' => self::$lineItem->updated_at,
        ];

        $this->assertEquals($expected, self::$lineItem->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$lineItem->unit_cost = 20;
        $this->assertTrue(self::$lineItem->save());
        $this->assertEquals(20, self::$lineItem->amount);
        $this->assertEquals(20, self::$invoice->refresh()->total);
    }

    /**
     * @depends testEdit
     */
    public function testAddRate(): void
    {
        self::$lineItem->discounts = [[
            'amount' => 5,
            'coupon' => 'coupon', ]];
        $this->assertTrue(self::$lineItem->save());
        $this->assertEquals(15, self::$invoice->refresh()->total);

        $expected = [
            [
                'amount' => 5,
                'coupon' => self::$coupon->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
        ];

        $discounts = self::$lineItem->discounts();
        unset($discounts[0]['id']);
        unset($discounts[0]['object']);
        unset($discounts[0]['updated_at']);
        $this->assertEquals($expected, $discounts);

        $this->assertEquals([], self::$lineItem->taxes());
    }

    /**
     * @depends testAddRate
     */
    public function testEditRate(): void
    {
        $discounts = self::$lineItem->discounts();
        $expectedId = $discounts[0]['id'];
        $discounts = [
            [
                'amount' => 10,
                'coupon' => 'coupon2',
            ],
            $discounts[0],
        ];

        self::$lineItem->discounts = $discounts;
        $this->assertTrue(self::$lineItem->save());
        $this->assertEquals(5, self::$invoice->refresh()->total);

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

        $discounts = self::$lineItem->discounts();
        unset($discounts[0]['id']);
        unset($discounts[0]['object']);
        unset($discounts[0]['updated_at']);
        unset($discounts[1]['object']);
        unset($discounts[1]['updated_at']);
        $this->assertEquals($expected, $discounts);

        $this->assertEquals([], self::$lineItem->taxes());
    }

    /**
     * @depends testEditRate
     */
    public function testDeleteRate(): void
    {
        $discounts = self::$lineItem->discounts();
        unset($discounts[0]);

        self::$lineItem->discounts = $discounts;
        $this->assertTrue(self::$lineItem->save());
        $this->assertEquals(15, self::$invoice->refresh()->total);

        $expected = [
            [
                'amount' => 5,
                'coupon' => self::$coupon->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
        ];

        $discounts = self::$lineItem->discounts();
        unset($discounts[0]['id']);
        unset($discounts[0]['object']);
        unset($discounts[0]['updated_at']);
        $this->assertEquals($expected, $discounts);

        $this->assertEquals([], self::$lineItem->taxes());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$lineItem->delete());
    }

    public function testPreventEditClosed(): void
    {
        // testing invoice line item
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $this->runLineItemsBunchTest($invoice);

        // testing credit note line item
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->setInvoice($invoice);
        $creditNote = $this->prepareLineItem($creditNote);
        $this->assertClosed($creditNote);

        // testing estimate line item
        $estimate = new Estimate();
        $estimate->setCustomer(self::$customer);
        $this->runLineItemsBunchTest($estimate);

        // testing customer line item
        $lineItem = $this->createLineItem(self::$customer);
        $lineItem->saveOrFail();
        $lineItem->delete();
    }

    public function testDeleteGeneratesNegativeBalance(): void
    {
        self::$lineItem = $this->createLineItem(self::$invoice);
        self::$lineItem->unit_cost = 100;
        self::$lineItem->save();

        self::$lineItem2 = $this->createLineItem(self::$invoice);
        self::$lineItem2->save();

        self::$creditNote = new CreditNote();
        self::$creditNote->setCustomer(self::$customer);
        self::$creditNote->setInvoice(self::$invoice);
        self::$creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        self::$creditNote->saveOrFail();
        self::$invoice->refresh();

        self::$lineItem->saveOrFail();
        self::$invoice->refresh();
        $this->assertEquals(10, self::$invoice->balance);
    }

    /**
     * @depends testDeleteGeneratesNegativeBalance
     */
    public function testEditGeneratesNegativeBalance(): void
    {
        self::$lineItem->unit_cost = 0;

        $this->assertTrue(self::$lineItem->save());
        self::$invoice->refresh();
        $this->assertEquals(-90, self::$invoice->balance);
    }

    /**
     * @depends testDeleteGeneratesNegativeBalance
     */
    public function testCreateGeneratesNegativeBalance(): void
    {
        $lineItem = $this->createLineItem(self::$invoice);
        $lineItem->unit_cost = -20;

        $this->assertFalse($lineItem->save());
        self::$invoice->refresh();
        $this->assertEquals(-90, self::$invoice->balance);
    }

    /**
     * @depends testDeleteGeneratesNegativeBalance
     */
    public function testDeleteLastLineItemGeneratesNegativeBalance(): void
    {
        self::$lineItem2->delete();

        self::getService('test.transaction_manager')->start();
        $this->assertFalse(self::$lineItem->delete());
        self::getService('test.transaction_manager')->rollBack();

        self::$invoice->refresh();
        $this->assertEquals(-90, self::$invoice->balance);
    }

    public function testInactiveCustomer(): void
    {
        $line = new LineItem();
        $line->customer_id = (int) self::$inactiveCustomer->id();
        $line->type = 'product';
        $line->name = 'Test Item';
        $line->unit_cost = 10;
        $line->quantity = 1;
        $this->assertFalse($line->save());
        $this->assertEquals('This cannot be created because the customer is inactive', (string) $line->getErrors());
    }

    private function runLineItemsBunchTest(ReceivableDocument $parent): void
    {
        $parent = $this->prepareLineItem($parent);

        // unclosed unvoided save
        $lineItem = $parent->items[0];
        $lineItem->name = 'Test item 2';
        $this->assertTrue($lineItem->save());

        // voided save
        $parent->void();
        $this->assertVoided($parent);

        // closed save
        $parent->closed = false;
        $parent->voided = false;
        $parent->saveOrFail();
        $parent->closed = true;
        $parent->voided = false;
        $parent->saveOrFail();
        $this->assertClosed($parent);
    }

    private function assertVoided(ReceivableDocument $parent): void
    {
        $this->assertFalse($parent->items[0]->delete());
        $this->assertEquals('Your changes cannot be saved because this document is voided.', $parent->getErrors()[0]);

        $this->changeNameSaveAndAssert($parent, 'Test item voided');
    }

    private function assertClosed(ReceivableDocument $parent): void
    {
        $this->assertFalse($parent->items[0]->delete());
        $this->assertEquals('Your changes cannot be saved because this document is closed. Please re-open the document to make any changes.', $parent->getErrors()[0]);

        $this->changeNameSaveAndAssert($parent, 'Test item closed');
    }

    /**
     * this is to avoid indirect modification notice.
     */
    private function changeNameSaveAndAssert(ReceivableDocument $parent, string $name): void
    {
        $lineItem = $parent->items[0];
        $lineItem->name = $name;
        $this->assertFalse($lineItem->save());
        $this->assertEquals($name, $lineItem->refresh()->name);
    }

    private function prepareLineItem(ReceivableDocument $parent): ReceivableDocument
    {
        $lineItem = $this->createLineItem($parent)->toArray();
        // initial save
        $parent->setLineItems([$lineItem]);
        $parent->saveOrFail();

        return $parent;
    }

    private function createLineItem(Model $parent): LineItem
    {
        $lineItem = new LineItem();
        $lineItem->discountable = true;
        $lineItem->type = 'product';
        $lineItem->name = 'Test Item';
        $lineItem->unit_cost = 10;
        $lineItem->quantity = 1;
        $lineItem->setParent($parent);
        $lineItem->catalog_item = self::$item->id;

        return $lineItem;
    }
}
