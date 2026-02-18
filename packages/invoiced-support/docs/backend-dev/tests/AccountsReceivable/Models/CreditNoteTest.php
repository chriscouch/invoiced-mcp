<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\EmailVariables\CreditNoteEmailVariables;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\DocumentView;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\ValueObjects\CreditNoteStatus;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Files\Models\Attachment;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Exception\ModelException;
use App\Core\Search\Libs\SearchDocumentFactory;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Tests\AppTestCase;
use stdClass;

class CreditNoteTest extends AppTestCase
{
    private static CreditNote $creditNote2;
    private static CreditNote $voidedCreditNote;
    private static CreditNote $creditNoteDelete;
    private static Coupon $coupon2;
    private static User $ogUser;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInactiveCustomer();
        self::hasCoupon();
        self::hasTaxRate();
        self::hasFile();
        self::hasInvoice();

        self::$customer->taxes = [self::$taxRate->id];
        self::$customer->saveOrFail();

        self::$coupon2 = new Coupon();
        self::$coupon2->id = 'coupon2';
        self::$coupon2->name = 'Coupon';
        self::$coupon2->is_percent = false;
        self::$coupon2->value = 10;
        self::$coupon2->saveOrFail();

        self::$ogUser = self::getService('test.user_context')->get();
    }

    public function assertPostConditions(): void
    {
        self::getService('test.user_context')->set(self::$ogUser);
    }

    public function testUrl(): void
    {
        $creditNote = new CreditNote();
        $creditNote->tenant_id = (int) self::$company->id();
        $creditNote->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/credit_notes/'.self::$company->identifier.'/test', $creditNote->url);
    }

    public function testUrlNoCustomerPortal(): void
    {
        self::$company->features->disable('billing_portal');
        $creditNote = new CreditNote();
        $creditNote->tenant_id = (int) self::$company->id();
        $creditNote->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/credit_notes/'.self::$company->identifier.'/test', $creditNote->url);
        self::$company->features->enable('billing_portal');
    }

    public function testPdfUrl(): void
    {
        $creditNote = new CreditNote();
        $creditNote->tenant_id = (int) self::$company->id();
        $creditNote->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/credit_notes/'.self::$company->identifier.'/test/pdf', $creditNote->pdf_url);
    }

    public function testEventAssociations(): void
    {
        $creditNote = new CreditNote();
        $creditNote->customer = 100;
        $creditNote->invoice_id = 200;

        $expected = [
            ['customer', 100],
            ['invoice', 200],
        ];

        $this->assertEquals($expected, $creditNote->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);

        $expected = array_merge($creditNote->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
            'network_document' => null,
        ]);

        $this->assertEquals($expected, $creditNote->getEventObject());
    }

    public function testGetEmailVariables(): void
    {
        $creditNote = new CreditNote();
        $this->assertInstanceOf(CreditNoteEmailVariables::class, $creditNote->getEmailVariables());
    }

    public function testCannotCreateNegativeTotal(): void
    {
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [['unit_cost' => -100]];
        $this->assertFalse($creditNote->save());
    }

    public function testCreateInvalidCustomer(): void
    {
        $creditNote = new CreditNote();
        $creditNote->customer = 12384234;
        $this->assertFalse($creditNote->save());
    }

    public function testCannotCreateMismatchedCustomer(): void
    {
        $creditNote = new CreditNote();
        $creditNote->customer = -1234;
        $creditNote->setInvoice(self::$invoice);
        $creditNote->items = [['unit_cost' => 500]];
        $this->assertFalse($creditNote->save());
    }

    public function testCannotCreateMismatchedCurrency(): void
    {
        $creditNote = new CreditNote();
        $creditNote->setInvoice(self::$invoice);
        $creditNote->items = [['unit_cost' => 500]];
        $creditNote->currency = 'eur';
        $this->assertFalse($creditNote->save());
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$creditNote = new CreditNote();
        $this->assertTrue(self::$creditNote->create([
            'number' => 'CRE-001',
            'date' => mktime(0, 0, 0, 6, 12, 2014),
            'customer' => self::$customer->id(),
            'items' => [
                [
                    'quantity' => 1,
                    'description' => 'test',
                    'unit_cost' => 105.26,
                    'discounts' => [
                        'coupon',
                    ],
                ],
                [
                    'quantity' => 12.045,
                    'description' => 'fractional item',
                    'unit_cost' => 1,
                ],
                [
                    'quantity' => 10,
                    'description' => 'negative item',
                    'unit_cost' => -1,
                ],
            ],
            'discounts' => [
                'coupon',
            ],
            'taxes' => [
                'tax',
            ],
            'notes' => 'test',
            'attachments' => [self::$file->id()],
        ]));

        $this->assertEquals(self::$company->id(), self::$creditNote->tenant_id);
        $this->assertEquals('CRE-001', self::$creditNote->number);
        $this->assertEquals(107.31, self::$creditNote->subtotal);
        $this->assertEquals(101.80, self::$creditNote->total);
        $this->assertEquals(101.80, self::$creditNote->balance);
        $this->assertEquals(48, strlen(self::$creditNote->client_id));

        // should create an attachment
        $n = Attachment::where('parent_type', 'credit_note')
            ->where('parent_id', self::$creditNote)
            ->where('file_id', self::$file)
            ->count();
        $this->assertEquals(1, $n);

        self::$creditNote2 = new CreditNote();
        self::$creditNote2->create([
            'customer' => self::$customer->id(),
            'date' => time(),
            'items' => [
                [
                    'quantity' => 1,
                    'description' => 'test',
                    'unit_cost' => 100,
                ],
            ],
            'currency' => 'eur', ]);
        $this->assertEquals('CN-00001', self::$creditNote2->number);
        $this->assertEquals(48, strlen(self::$creditNote2->client_id));
        $this->assertNotEquals(self::$creditNote->client_id, self::$creditNote2->client_id);
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        $creditNote = new CreditNote();
        $errors = $creditNote->getErrors();

        // should not be able to create credit note with non-unique #
        $creditNote->setCustomer(self::$customer);
        $creditNote->number = 'CRE-001';
        $this->assertFalse($creditNote->save());

        $this->assertCount(1, $errors);
        $this->assertEquals('The given credit note number has already been taken: CRE-001', $errors->all()[0]);
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$creditNote, EventType::CreditNoteCreated);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$creditNote->name = 'New Name';
        $this->assertTrue(self::$creditNote->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$creditNote, EventType::CreditNoteUpdated);
    }

    public function testCannotEditCustomer(): void
    {
        $creditNote = new CreditNote(['id' => -100, 'customer' => -1, 'tenant_id' => self::$company->id()]);
        $creditNote->customer = -2;
        $this->assertFalse($creditNote->save());
        $this->assertEquals(['Invalid request parameter `customer`. The customer cannot be modified.'], $creditNote->getErrors()->all());
    }

    public function testNextCreditNoteNumberCollisions(): void
    {
        $sequence = self::$creditNote->getNumberingSequence();
        $sequence->setNext(100);

        // create some credit notes to test collision prevention
        for ($i = 0; $i < 10; ++$i) {
            $creditNote = new CreditNote();
            $creditNote->setCustomer(self::$customer);
            $creditNote->number = 'CN-00'.(100 + $i);
            $this->assertTrue($creditNote->save());
        }

        // test next credit note #
        $this->assertEquals(110, $sequence->nextNumber());
    }

    /**
     * @depends testCreate
     */
    public function testFindClientId(): void
    {
        $this->assertNull(CreditNote::findClientId(''));
        $this->assertNull(CreditNote::findClientId('1234'));

        $this->assertEquals(self::$creditNote->id(), CreditNote::findClientId(self::$creditNote->client_id)->id()); /* @phpstan-ignore-line */

        $old = self::$creditNote->client_id;
        self::$creditNote->refreshClientId();
        $this->assertNotEquals($old, self::$creditNote->client_id);

        // set client ID in the past
        self::$creditNote->refreshClientId(false, strtotime('-1 year'));
        /** @var CreditNote $obj */
        $obj = CreditNote::findClientId(self::$creditNote->client_id);

        // set the client ID to expire soon
        self::$creditNote->refreshClientId(false, strtotime('+29 days'));
        /** @var CreditNote $obj */
        $obj = CreditNote::findClientId(self::$creditNote->client_id);
    }

    public function testCannotVoidCredited(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This credit note cannot be voided because it has been added to the customer\'s credit balance.');

        $creditNote = new CreditNote();
        $creditNote->amount_credited = 100;
        $creditNote->void();
    }

    public function testCannotVoidRefunded(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This credit note cannot be voided because it has a refund applied.');

        $creditNote = new CreditNote();
        $creditNote->amount_refunded = 100;
        $creditNote->void();
    }

    public function testCannotVoidInvoiced(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This credit note cannot be voided because it has been applied to an invoice.');

        $creditNote = new CreditNote();
        $creditNote->amount_applied_to_invoice = 100;
        $creditNote->void();
    }

    public function testVoidAlreadyVoided(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This document has already been voided.');

        $creditNote = new CreditNote();
        $creditNote->voided = true;
        $creditNote->void();
    }

    public function testVoid(): void
    {
        self::$voidedCreditNote = new CreditNote();
        self::$voidedCreditNote->setCustomer(self::$customer);
        self::$voidedCreditNote->items = [['unit_cost' => 100]];
        self::$voidedCreditNote->saveOrFail();

        self::$voidedCreditNote->void();

        $this->assertTrue(self::$voidedCreditNote->voided);
        $this->assertBetween(time() - self::$voidedCreditNote->date_voided, 0, 3);
        $this->assertEquals('voided', self::$voidedCreditNote->status);
        $this->assertNull(self::$voidedCreditNote->url);
        $this->assertNull(self::$voidedCreditNote->pdf_url);
        $this->assertEquals(0, self::$voidedCreditNote->balance);
        $this->assertFalse(self::$voidedCreditNote->paid);
        $this->assertFalse(self::$voidedCreditNote->closed);

        // cannot edit once voided
        self::$voidedCreditNote->items = [['unit_cost' => 1000]];
        $this->assertFalse(self::$voidedCreditNote->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$creditNote->id,
            'object' => 'credit_note',
            'customer' => self::$customer->id(),
            'name' => 'New Name',
            'currency' => 'usd',
            'items' => [
                [
                    'catalog_item' => null,
                    'quantity' => 1,
                    'description' => 'test',
                    'unit_cost' => 105.26,
                    'name' => '',
                    'type' => null,
                    'amount' => 105.26,
                    'discountable' => true,
                    'discounts' => [
                        [
                            'coupon' => self::$coupon->toArray(),
                            'amount' => 5.26,
                            'expires' => null,
                            'from_payment_terms' => false,
                        ],
                    ],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
                [
                    'catalog_item' => null,
                    'quantity' => 12.045,
                    'description' => 'fractional item',
                    'unit_cost' => 1,
                    'name' => '',
                    'amount' => 12.05,
                    'type' => null,
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
                [
                    'catalog_item' => null,
                    'quantity' => 10,
                    'description' => 'negative item',
                    'unit_cost' => -1,
                    'type' => null,
                    'name' => '',
                    'amount' => -10,
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
            ],
            'subtotal' => 107.31,
            'discounts' => [
                [
                    'coupon' => self::$coupon->toArray(),
                    'amount' => 5.10,
                    'expires' => null,
                    'from_payment_terms' => false,
                ],
            ],
            'shipping' => [],
            'taxes' => [
                [
                    'tax_rate' => self::$taxRate->toArray(),
                    'amount' => 4.85,
                ],
            ],
            'total' => 101.80,
            'balance' => 101.80,
            'paid' => false,
            'notes' => 'test',
            'number' => 'CRE-001',
            'date' => mktime(0, 0, 0, 6, 12, 2014),
            'purchase_order' => null,
            'network_document_id' => null,
            'draft' => false,
            'closed' => false,
            'url' => 'http://invoiced.localhost:1234/credit_notes/'.self::$company->identifier.'/'.self::$creditNote->client_id,
            'pdf_url' => 'http://invoiced.localhost:1234/credit_notes/'.self::$company->identifier.'/'.self::$creditNote->client_id.'/pdf',
            'status' => CreditNoteStatus::OPEN,
            'metadata' => new stdClass(),
            'created_at' => self::$creditNote->created_at,
            'updated_at' => self::$creditNote->updated_at,
            'invoice' => null,
        ];

        $arr = self::$creditNote->toArray();

        // remove item ids
        foreach ($arr['items'] as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
            foreach (['discounts', 'taxes'] as $type) {
                foreach ($item[$type] as &$rate) {
                    unset($rate['id']);
                    unset($rate['object']);
                    unset($rate['updated_at']);
                }
            }
        }

        // remove applied rate ids
        foreach (['discounts', 'taxes', 'shipping'] as $type) {
            foreach ($arr[$type] as &$rate) {
                unset($rate['id']);
                unset($rate['object']);
                unset($rate['updated_at']);
            }
        }

        $this->assertEquals($expected, $arr);
    }

    /**
     * @depends testCreate
     */
    public function testToSearchDocument(): void
    {
        $expected = [
            'name' => 'New Name',
            'number' => 'CRE-001',
            'purchase_order' => null,
            'currency' => 'usd',
            'subtotal' => 107.31,
            'total' => 101.80,
            'balance' => 101.80,
            'date' => mktime(0, 0, 0, 6, 12, 2014),
            'status' => CreditNoteStatus::OPEN,
            'metadata' => [],
            '_customer' => self::$customer->id(),
            'customer' => [
                'name' => self::$customer->name,
            ],
        ];

        $this->assertEquals($expected, (new SearchDocumentFactory())->make(self::$creditNote));
    }

    /**
     * @depends testCreate
     */
    public function testFindByCreditNoteNo(): void
    {
        // logout
        self::getService('test.user_context')->set(new User(['id' => -1]));

        // lookup credit note by credit note #
        $creditNote = CreditNote::where('number', self::$creditNote->number)
            ->oneOrNull();

        $this->assertInstanceOf(CreditNote::class, $creditNote);
        $this->assertEquals(self::$creditNote->id(), $creditNote->id());
    }

    public function testFindByNonexistentCreditNoteNo(): void
    {
        // logout
        self::getService('test.user_context')->set(new User(['id' => -1]));

        // lookup non-existent credit note #
        $this->assertNull(CreditNote::where('number', 'doesnotexist')->oneOrNull());
    }

    /**
     * @depends testCreate
     */
    public function testEmail(): void
    {
        $emailTemplate = (new DocumentEmailTemplateFactory())->get(self::$creditNote);
        self::getService('test.email_spool')->spoolDocument(self::$creditNote, $emailTemplate)->flush();

        $this->assertTrue(self::$creditNote->sent);
    }

    /**
     * @depends testCreate
     */
    public function testAddView(): void
    {
        EventSpool::enable();

        $documentViewTracker = self::getService('test.document_view_tracker');

        // views should not be duplicated for the same
        // document/user agent/ip combo
        $first = false;
        for ($i = 0; $i < 5; ++$i) {
            $view = $documentViewTracker->addView(self::$creditNote, 'firefox', '10.0.0.1');

            // if the view is a duplicate then it should
            // simply return the past object
            if ($first) {
                $this->assertEquals($first->id(), $view->id());

                continue;
            }

            $first = $view;

            // verify the view object
            $this->assertInstanceOf(DocumentView::class, $view);
            $this->assertEquals('credit_note', $view->document_type);
            $this->assertEquals(self::$creditNote->id(), $view->document_id);
            $this->assertEquals('firefox', $view->user_agent);
            $this->assertEquals('10.0.0.1', $view->ip);

            // verify the document's status
            $this->assertTrue(self::$creditNote->viewed);
            $this->assertEquals(CreditNoteStatus::OPEN, self::$creditNote->status);

            // verify the event
            self::getService('test.event_spool')->flush(); // write out events
            $event = Event::where('object_type_id', ObjectType::DocumentView->value)
                ->where('object_id', $view)
                ->where('type_id', EventType::CreditNoteViewed->toInteger())
                ->oneOrNull();

            $this->assertInstanceOf(Event::class, $event);
            $associations = $event->getAssociations();
            $this->assertEquals(self::$creditNote->id(), $associations['credit_note']);
            $this->assertEquals(self::$customer->id(), $associations['customer']);
            $this->assertNotEquals(false, $event->href);
        }
    }

    /**
     * @depends testCreate
     */
    public function testRecalculate(): void
    {
        self::$creditNote->closed = false;
        $this->assertTrue(self::$creditNote->save());

        $this->assertTrue(self::$creditNote->recalculate());
    }

    /**
     * @depends testCreate
     */
    public function testReorderLineItems(): void
    {
        $items = self::$creditNote->items();
        $expectedOrder = [$items[2]['id'], $items[0]['id'], $items[1]['id']];
        $items = [
            $items[2],
            $items[0],
            $items[1], ];
        self::$creditNote->items = $items;
        $this->assertTrue(self::$creditNote->save());

        $newItems = self::$creditNote->items(true);
        $newOrder = [];
        foreach ($newItems as $item) {
            $newOrder[] = $item['id'];
        }
        $this->assertEquals($expectedOrder, $newOrder);
    }

    /**
     * @depends testCreate
     */
    public function testSetNewLineItems(): void
    {
        self::$creditNote->items = [['quantity' => 8, 'unit_cost' => 100]];
        $this->assertTrue(self::$creditNote->save());

        $this->assertEquals(800.00, self::$creditNote->subtotal);
        $this->assertEquals(798, self::$creditNote->total);

        $expected = [
            [
                'type' => null,
                'catalog_item' => null,
                'name' => '',
                'quantity' => 8,
                'unit_cost' => 100,
                'amount' => 800,
                'description' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
        ];
        $items = self::$creditNote->items();
        unset($items[0]['id']);
        unset($items[0]['created_at']);
        unset($items[0]['updated_at']);
        unset($items[0]['object']);
        $this->assertEquals($expected, $items);
    }

    /**
     * @depends testCreate
     */
    public function testEditExistingLineItem(): void
    {
        $items = self::$creditNote->items();
        $ids = array_map(function ($item) {
            return $item['id'];
        }, $items);

        $items[0]['unit_cost'] = 700;

        self::$creditNote->items = $items;
        $this->assertTrue(self::$creditNote->save());

        $newIds = array_map(function ($item) {
            return $item['id'];
        }, self::$creditNote->items());
        $this->assertEquals($ids, $newIds);
    }

    /**
     * @depends testCreate
     */
    public function testEditNonUnique(): void
    {
        // should not be able to edit credit notes with non-unique #
        self::$creditNote2->number = 'CRE-001';
        $this->assertFalse(self::$creditNote2->save());
    }

    /**
     * @depends testEditExistingLineItem
     */
    public function testEditRate(): void
    {
        // subtotal at this point is: 5600
        // discounts = 280
        // taxes = 266

        self::$creditNote->closed = false;

        $discounts = self::$creditNote->discounts();
        $expectedId = $discounts[0]['id'];
        $discounts = [
            [
                'coupon' => 'coupon2',
            ],
            $discounts[0],
        ];

        self::$creditNote->discounts = $discounts;
        $this->assertTrue(self::$creditNote->save());
        $this->assertEquals(5575.5, self::$creditNote->total);

        $expected = [
            [
                'amount' => 10,
                'coupon' => self::$coupon2->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
            [
                'id' => $expectedId,
                'amount' => 280,
                'coupon' => self::$coupon->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
        ];

        $discounts = self::$creditNote->discounts();
        unset($discounts[0]['id']);
        unset($discounts[0]['object']);
        unset($discounts[0]['updated_at']);
        unset($discounts[1]['object']);
        unset($discounts[1]['updated_at']);
        $this->assertEquals($expected, $discounts);

        $expected = [
            [
                'amount' => 265.5,
                'tax_rate' => self::$taxRate->toArray(),
            ],
        ];

        $taxes = self::$creditNote->taxes();
        unset($taxes[0]['id']);
        unset($taxes[0]['object']);
        unset($taxes[0]['updated_at']);
        $this->assertEquals($expected, $taxes);

        $expected = [];
        $this->assertEquals($expected, self::$creditNote->shipping());
    }

    /**
     * @depends testEditRate
     */
    public function testDeleteRate(): void
    {
        // subtotal at this point is: 5600
        // discounts = 290
        // taxes = 265.5

        $discounts = self::$creditNote->discounts();
        unset($discounts[0]);

        self::$creditNote->discounts = $discounts;
        $this->assertTrue(self::$creditNote->save());
        $this->assertEquals(5586, self::$creditNote->total);

        $expected = [
            [
                'amount' => 280,
                'coupon' => self::$coupon->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
        ];

        $discounts = self::$creditNote->discounts();
        unset($discounts[0]['id']);
        unset($discounts[0]['object']);
        unset($discounts[0]['updated_at']);
        $this->assertEquals($expected, $discounts);

        $expected = [
            [
                'amount' => 266,
                'tax_rate' => self::$taxRate->toArray(),
            ],
        ];

        $taxes = self::$creditNote->taxes();
        unset($taxes[0]['id']);
        unset($taxes[0]['object']);
        unset($taxes[0]['updated_at']);
        $this->assertEquals($expected, $taxes);

        $expected = [];
        $this->assertEquals($expected, self::$creditNote->shipping());

        // delete all rates
        self::$creditNote->discounts = [];
        self::$creditNote->taxes = [];
        $this->assertTrue(self::$creditNote->save());

        $this->assertEquals([], self::$creditNote->discounts());
        $this->assertEquals([], self::$creditNote->taxes());
        $this->assertEquals([], self::$creditNote->shipping());
    }

    public function testEditTotalDecrease(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100, 'taxable' => false]];
        $invoice->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->setInvoice($invoice);
        $creditNote->items = [['unit_cost' => 75, 'taxable' => false]];
        $creditNote->saveOrFail();
        $this->assertEquals(75, $invoice->refresh()->amount_credited);
        $this->assertEquals(25, $invoice->balance);

        $creditNote->closed = false;
        $creditNote->items = [['unit_cost' => 50, 'taxable' => false]];
        $creditNote->saveOrFail();
        $this->assertEquals(-25, $creditNote->balance);
    }

    public function testEditTotalIncrease(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100, 'taxable' => false]];
        $invoice->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->setInvoice($invoice);
        $creditNote->items = [['unit_cost' => 75, 'taxable' => false]];
        $creditNote->saveOrFail();
        $this->assertEquals(75, $invoice->refresh()->amount_credited);
        $this->assertEquals(25, $invoice->balance);

        $creditNote->closed = false;
        $creditNote->items = [['unit_cost' => 150, 'taxable' => false]];
        $creditNote->saveOrFail();
    }

    /**
     * @depends testCreate
     */
    public function testEditAttachments(): void
    {
        self::$creditNote->name = 'Test';
        self::$creditNote->saveOrFail();

        // should keep the attachment
        $n = Attachment::where('parent_type', 'credit_note')
            ->where('parent_id', self::$creditNote)
            ->where('file_id', self::$file)
            ->count();
        $this->assertEquals(1, $n);

        self::$creditNote->attachments = [];
        $this->assertTrue(self::$creditNote->save());

        // should delete the attachment
        $n = Attachment::where('parent_type', 'credit_note')
            ->where('parent_id', self::$creditNote)
            ->where('file_id', self::$file)
            ->count();
        $this->assertEquals(0, $n);
    }

    /**
     * @depends testCreate
     */
    public function testMetadata(): void
    {
        $metadata = self::$creditNote->metadata;
        $metadata->test = true;
        self::$creditNote->metadata = $metadata;
        self::$creditNote->saveOrFail();
        $this->assertEquals((object) ['test' => true], self::$creditNote->metadata);

        self::$creditNote->metadata = (object) ['internal.id' => '12345'];
        self::$creditNote->saveOrFail();
        $this->assertEquals((object) ['internal.id' => '12345'], self::$creditNote->metadata);

        self::$creditNote->metadata = (object) ['array' => [], 'object' => new stdClass()];
        self::$creditNote->saveOrFail();
        $this->assertEquals((object) ['array' => [], 'object' => new stdClass()], self::$creditNote->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata(): void
    {
        self::$creditNote->metadata = (object) [str_pad('', 41) => 'fail'];
        $this->assertFalse(self::$creditNote->save());

        self::$creditNote->metadata = (object) ['fail' => str_pad('', 256)];
        $this->assertFalse(self::$creditNote->save());

        self::$creditNote->metadata = (object) array_fill(0, 11, 'fail');
        $this->assertFalse(self::$creditNote->save());
    }

    public function testCannotDeleteCredited(): void
    {
        $creditNote = new CreditNote(['id' => -1]);
        $creditNote->amount_credited = 100;
        $this->assertFalse($creditNote->delete());
        $this->assertEquals('This credit note cannot be voided because it has been added to the customer\'s credit balance.', $creditNote->getErrors()[0]['message']);
    }

    public function testCannotDeleteRefunded(): void
    {
        $creditNote = new CreditNote(['id' => -1]);
        $creditNote->amount_refunded = 100;
        $this->assertFalse($creditNote->delete());
        $this->assertEquals('This credit note cannot be voided because it has a refund applied.', $creditNote->getErrors()[0]['message']);
    }

    public function testCannotDeleteInvoiced(): void
    {
        $creditNote = new CreditNote(['id' => -1]);
        $creditNote->amount_applied_to_invoice = 100;
        $this->assertFalse($creditNote->delete());
        $this->assertEquals('This credit note cannot be voided because it has been applied to an invoice.', $creditNote->getErrors()[0]['message']);
    }

    public function testDelete(): void
    {
        self::$creditNoteDelete = new CreditNote();
        self::$creditNoteDelete->setCustomer(self::$customer);
        self::$creditNoteDelete->items = [['unit_cost' => 75, 'taxable' => false]];
        self::$creditNoteDelete->saveOrFail();

        EventSpool::enable();
        $this->assertTrue(self::$creditNoteDelete->delete());
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$creditNoteDelete, EventType::CreditNoteDeleted);
    }

    public function testLegacyMode(): void
    {
        self::$customer->taxable = false;
        self::$customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->setInvoice($invoice);
        $creditNote->items = [['unit_cost' => 100]];
        $creditNote->saveOrFail();

        $this->assertTrue($creditNote->closed);
        $this->assertTrue($creditNote->paid);
        $this->assertEquals(0, $creditNote->balance);
        $this->assertTrue($invoice->closed);
        $this->assertTrue($invoice->paid);
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(100.0, $invoice->amount_credited);

        $transaction = Transaction::where('credit_note_id', $creditNote->id())->oneOrNull();
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(-100, $transaction->amount);
        $this->assertEquals($invoice->id(), $transaction->invoice);

        // editing the transaction should be allowed
        $transaction->amount = -50;
        $transaction->saveOrFail();

        $invoice->refresh();
        $creditNote->refresh();

        $this->assertFalse($creditNote->closed);
        $this->assertFalse($creditNote->paid);
        $this->assertEquals(50, $creditNote->balance);
        $this->assertFalse($creditNote->closed);
        $this->assertFalse($invoice->paid);
        $this->assertEquals(50, $invoice->balance);

        // edit the transaction back to original amount
        $transaction->amount = -100;
        $transaction->saveOrFail();

        // deleting the transaction should re-open the invoice and credit note
        $this->assertTrue($transaction->delete());

        $invoice->refresh();
        $creditNote->refresh();

        $this->assertFalse($creditNote->closed);
        $this->assertFalse($creditNote->paid);
        $this->assertEquals(100, $creditNote->balance);
        $this->assertFalse($creditNote->closed);
        $this->assertFalse($invoice->paid);
        $this->assertEquals(100, $invoice->balance);
    }

    public function testDraftInvoiceApplication(): void
    {
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->draft = true;
        $creditNote->items = [['unit_cost' => 100]];
        $creditNote->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 0;
        $payment->applied_to = [
            [
                'type' => 'credit_note',
                'credit_note' => $creditNote,
                'document_type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 100,
            ],
        ];
        $payment->saveOrFail();
        $this->assertFalse($creditNote->draft);
    }

    public function testDraftCreditBalanceApplication(): void
    {
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->draft = true;
        $creditNote->items = [['unit_cost' => 100]];
        $creditNote->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 0;
        $payment->applied_to = [
            [
                'type' => 'credit_note',
                'credit_note' => $creditNote,
                'amount' => 100,
            ],
        ];
        $payment->saveOrFail();
        $this->assertFalse($creditNote->draft);
    }

    public function testInactiveCustomer(): void
    {
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$inactiveCustomer);
        $creditNote->items = [['unit_cost' => 100]];
        $this->assertFalse($creditNote->save());
        $this->assertEquals('This cannot be created because the customer is inactive', (string) $creditNote->getErrors());
    }

    public function testIssuePermissions(): void
    {
        self::hasCustomer();
        $role = new Role();
        $role->name = 'test';
        $role->saveOrFail();
        $member = Member::query()->one();
        $member->role = $role->id;
        $member->saveOrFail();
        $requester = ACLModelRequester::get();
        ACLModelRequester::set($member);

        $reset = function ($draft) use ($member, $role): CreditNote {
            // cleaning the cache
            $member->role = $role->id;
            $member->setRelation('role', $role);
            $creditNote = new CreditNote();
            $creditNote->setCustomer(self::$customer);
            $creditNote->draft = $draft;
            $creditNote->saveOrFail();

            return $creditNote;
        };

        // no permissions
        try {
            $reset(false);
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }

        // issue permissions only
        $role->credit_notes_create = false;
        $role->credit_notes_issue = true;
        $role->saveOrFail();

        // create and issue permissions
        $role->credit_notes_create = true;
        $role->credit_notes_issue = true;
        $role->saveOrFail();
        $creditNote = $reset(true);
        $reset(false);

        $role->credit_notes_edit = true;
        $role->credit_notes_issue = false;
        $role->saveOrFail();
        // create new  draft credit note
        $creditNote = $reset(true);
        // no issue only
        $creditNote->draft = false;
        try {
            $creditNote->saveOrFail();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }
        // no issue + edit
        $creditNote->name = 'test';
        try {
            $creditNote->saveOrFail();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException) {
        }
        // yes edit only
        $creditNote->draft = true;
        $creditNote->saveOrFail();

        $role->credit_notes_edit = true;
        $role->credit_notes_issue = true;
        $role->saveOrFail();
        // create new  draft credit note
        $creditNote = $reset(true);
        // yes issue + edit
        $creditNote->draft = true;
        $creditNote->name = 'test';
        $creditNote->saveOrFail();

        $member->role = Role::ADMINISTRATOR;
        $member->saveOrFail();
        ACLModelRequester::set($requester);
        $this->assertTrue(true);
    }

    public function testVoidPermissions(): void
    {
        self::hasCustomer();
        $requester = ACLModelRequester::get();

        $role = new Role();
        $role->name = 'test';
        $role->saveOrFail();
        $member = Member::query()->one();
        $member->role = $role->id;
        $member->saveOrFail();

        $reset = function () use ($member, $requester, $role) {
            // cleaning the cache
            $member->role = $role->id;
            ACLModelRequester::set($requester);
            self::hasUnappliedCreditNote();
            ACLModelRequester::set($member);
        };

        $reset();
        try {
            self::$creditNote->void();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }

        $reset();
        $role->credit_notes_edit = true;
        $role->saveOrFail();
        try {
            self::$creditNote->void();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }

        $reset();
        $role->credit_notes_edit = false;
        $role->credit_notes_void = true;
        $role->saveOrFail();
        $member->setRelation('role', $role);
        self::$creditNote->void();

        $reset();
        $role->credit_notes_edit = true;
        $role->saveOrFail();
        self::$creditNote->void();

        $member->role = Role::ADMINISTRATOR;
        $member->saveOrFail();
        ACLModelRequester::set($requester);
        $this->assertTrue(true);
    }
}
