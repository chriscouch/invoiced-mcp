<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\EmailVariables\EstimateEmailVariables;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\DocumentView;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\EstimateApproval;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ShippingDetail;
use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Files\Models\Attachment;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\Core\Search\Libs\SearchDocumentFactory;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Tests\AppTestCase;
use stdClass;

class EstimateTest extends AppTestCase
{
    private static Customer $customer2;
    private static Estimate $estimate2;
    private static Estimate $shippingEstimate;
    private static Estimate $voidedEstimate;
    private static Coupon $coupon2;
    private static User $ogUser;
    private static ?Model $requester;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInactiveCustomer();
        self::hasCoupon();
        self::hasTaxRate();
        self::hasFile();

        self::$customer->taxes = [self::$taxRate->id];
        self::$customer->save();

        self::$customer2 = new Customer();
        self::$customer2->name = 'Test 2';
        self::$customer2->saveOrFail();

        self::$coupon2 = new Coupon();
        self::$coupon2->id = 'coupon2';
        self::$coupon2->name = 'Coupon';
        self::$coupon2->is_percent = false;
        self::$coupon2->value = 10;
        self::$coupon2->saveOrFail();

        self::$ogUser = self::getService('test.user_context')->get();
        self::$requester = ACLModelRequester::get();
    }

    public function assertPostConditions(): void
    {
        self::getService('test.user_context')->set(self::$ogUser);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        ACLModelRequester::set(self::$requester);
    }

    public function testUrl(): void
    {
        $estimate = new Estimate();
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/estimates/'.self::$company->identifier.'/test', $estimate->url);
    }

    public function testUrlNoCustomerPortal(): void
    {
        self::$company->features->disable('billing_portal');
        $estimate = new Estimate();
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/estimates/'.self::$company->identifier.'/test', $estimate->url);
        self::$company->features->enable('billing_portal');
    }

    public function testPdfUrl(): void
    {
        $estimate = new Estimate();
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/estimates/'.self::$company->identifier.'/test/pdf', $estimate->pdf_url);
    }

    public function testGetEmailVariables(): void
    {
        $estimate = new Estimate();
        $this->assertInstanceOf(EstimateEmailVariables::class, $estimate->getEmailVariables());
    }

    public function testEventAssociations(): void
    {
        $estimate = new Estimate();
        $estimate->customer = 100;

        $expected = [
            ['customer', 100],
        ];

        $this->assertEquals($expected, $estimate->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $estimate = new Estimate();
        $estimate->setCustomer(self::$customer);

        $expected = array_merge($estimate->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
            'network_document' => null,
        ]);

        $this->assertEquals($expected, $estimate->getEventObject());
    }

    public function testCannotCreateNegativeTotal(): void
    {
        $estimate = new Estimate();
        $estimate->setCustomer(self::$customer);
        $estimate->items = [['unit_cost' => -100]];
        $this->assertFalse($estimate->save());
    }

    public function testCreateInvalidCustomer(): void
    {
        $estimate = new Estimate();
        $estimate->customer = 12384234;
        $this->assertFalse($estimate->save());
    }

    public function testCreateInvalidInvoice(): void
    {
        $estimate = new Estimate();
        $estimate->setCustomer(self::$customer);
        $estimate->invoice_id = 12384234;
        $this->assertFalse($estimate->save());
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$estimate = new Estimate();
        $this->assertTrue(self::$estimate->create([
            'customer' => self::$customer->id(),
            'number' => 'QUO-001',
            'date' => mktime(0, 0, 0, 6, 12, 2014),
            'payment_terms' => 'NET 30',
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
            'currency' => 'eur',
            'notes' => 'test',
            'attachments' => [self::$file->id()],
        ]));

        $this->assertEquals(self::$company->id(), self::$estimate->tenant_id);
        $this->assertEquals('QUO-001', self::$estimate->number);
        $this->assertEquals(107.31, self::$estimate->subtotal);
        $this->assertEquals(101.80, self::$estimate->total);
        $this->assertEquals(48, strlen(self::$estimate->client_id));

        // should create an attachment
        $n = Attachment::where('parent_type', 'estimate')
            ->where('parent_id', self::$estimate)
            ->where('file_id', self::$file)
            ->count();
        $this->assertEquals(1, $n);

        self::$estimate2 = new Estimate();
        $this->assertTrue(self::$estimate2->create([
            'customer' => self::$customer2->id(),
            'date' => time(),
            'items' => [
                [
                    'quantity' => 1,
                    'description' => 'test',
                    'unit_cost' => 100,
                ],
            ],
            'currency' => 'eur', ]));
        $this->assertEquals('EST-00001', self::$estimate2->number);
        $this->assertEquals(48, strlen(self::$estimate2->client_id));
        $this->assertNotEquals(self::$estimate->client_id, self::$estimate2->client_id);
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        $estimate = new Estimate();
        $errors = $estimate->getErrors();

        // should not be able to create estimate with non-unique #
        $estimate->setCustomer(self::$customer);
        $estimate->number = 'QUO-001';
        $this->assertFalse($estimate->save());

        $this->assertCount(1, $errors);
        $this->assertEquals('The given estimate number has already been taken: QUO-001', $errors->all()[0]);
    }

    public function testCreateShipTo(): void
    {
        $estimate = new Estimate();
        $estimate->setCustomer(self::$customer);
        $estimate->ship_to = [/* @phpstan-ignore-line */
            'name' => 'Test',
            'address1' => '1234 main st',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78735',
            'country' => 'US',
        ];
        $estimate->items = [['unit_cost' => 100]];
        $estimate->saveOrFail();
        self::$shippingEstimate = $estimate;

        $shipping2 = $estimate->ship_to;
        $this->assertInstanceOf(ShippingDetail::class, $shipping2);
        $expected = [
            'address1' => '1234 main st',
            'address2' => null,
            'attention_to' => null,
            'city' => 'Austin',
            'country' => 'US',
            'name' => 'Test',
            'postal_code' => '78735',
            'state' => 'TX',
        ];
        $shipTo = $shipping2->toArray();
        unset($shipTo['created_at']);
        unset($shipTo['updated_at']);
        $this->assertEquals($expected, $shipTo);
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$estimate, EventType::EstimateCreated);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$estimate->name = 'New Name';
        $this->assertTrue(self::$estimate->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$estimate, EventType::EstimateUpdated);
    }

    /**
     * @depends testCreate
     */
    public function testEditInvalidInvoice(): void
    {
        self::$estimate->invoice_id = 123428420;
        $this->assertFalse(self::$estimate->save());
        unset(self::$estimate->invoice_id);
    }

    public function testCannotEditCustomer(): void
    {
        $estimate = new Estimate(['id' => -100, 'customer' => -1, 'tenant_id' => self::$company->id()]);
        $estimate->customer = -2;
        $this->assertFalse($estimate->save());
        $this->assertEquals(['Invalid request parameter `customer`. The customer cannot be modified.'], $estimate->getErrors()->all());
    }

    /**
     * @depends testCreateShipTo
     */
    public function testEditShipTo(): void
    {
        // change the address
        self::$shippingEstimate->ship_to = [/* @phpstan-ignore-line */
            'name' => 'Test',
            'address1' => '5301 southwest parkway',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78735',
            'country' => 'US',
        ];
        self::$shippingEstimate->saveOrFail();

        $shipping2 = self::$shippingEstimate->ship_to;
        $this->assertInstanceOf(ShippingDetail::class, $shipping2);
        $expected = [
            'address1' => '5301 southwest parkway',
            'address2' => null,
            'attention_to' => null,
            'city' => 'Austin',
            'country' => 'US',
            'name' => 'Test',
            'postal_code' => '78735',
            'state' => 'TX',
        ];
        $shipTo = $shipping2->toArray();
        unset($shipTo['created_at']);
        unset($shipTo['updated_at']);
        $this->assertEquals($expected, $shipTo);

        // remove the ship to altogether
        self::$shippingEstimate->ship_to = null;
        self::$shippingEstimate->saveOrFail();

        $this->assertNull(self::$shippingEstimate->ship_to);
    }

    public function testNextEstimateNumberCollisions(): void
    {
        $sequence = self::$estimate->getNumberingSequence();
        $sequence->setNext(100);

        // create some invoices to test collision prevention
        for ($i = 0; $i < 10; ++$i) {
            $estimate = new Estimate();
            $estimate->setCustomer(self::$customer);
            $estimate->number = 'EST-00'.(100 + $i);
            $this->assertTrue($estimate->save());
        }

        // test next estimate #
        $this->assertEquals(110, $sequence->nextNumber());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $estimates = Estimate::all();

        $this->assertCount(13, $estimates);

        // look for our known estimates
        $find = [self::$estimate->id(), self::$estimate2->id()];
        foreach ($estimates as $estimate) {
            if (false !== ($key = array_search($estimate->id(), $find))) {
                unset($find[$key]);
            }
        }
        $this->assertCount(0, $find);
    }

    /**
     * @depends testCreate
     */
    public function testQueryCustomFieldRestriction(): void
    {
        $member = new Member();
        $member->role = Role::ADMINISTRATOR;
        $member->setUser(self::getService('test.user_context')->get());
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];

        ACLModelRequester::set($member);

        $this->assertEquals(0, Estimate::count());

        // update the customer territory
        self::$customer->metadata = (object) ['territory' => 'Texas'];
        self::$customer->saveOrFail();
        self::$estimate->setRelation('customer', self::$customer);

        $estimates = Estimate::all();
        $this->assertCount(12, $estimates);
        $this->assertEquals(self::$estimate->id(), $estimates[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testFindClientId(): void
    {
        $this->assertNull(Estimate::findClientId(''));
        $this->assertNull(Estimate::findClientId('1234'));

        $this->assertEquals(self::$estimate->id(), Estimate::findClientId(self::$estimate->client_id)->id()); /* @phpstan-ignore-line */

        $old = self::$estimate->client_id;
        self::$estimate->refreshClientId();
        $this->assertNotEquals($old, self::$estimate->client_id);

        // set client ID in the past
        self::$estimate->refreshClientId(false, strtotime('-1 year'));
        /** @var Estimate $obj */
        $obj = Estimate::findClientId(self::$estimate->client_id);

        // set the client ID to expire soon
        self::$estimate->refreshClientId(false, strtotime('+29 days'));
        /** @var Estimate $obj */
        $obj = Estimate::findClientId(self::$estimate->client_id);
    }

    public function testCannotVoidPartialPayment(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This estimate cannot be voided because it has a deposit payment applied.');

        $estimate = new Estimate();
        $estimate->deposit_paid = true;
        $estimate->void();
    }

    public function testCannotVoidAlreadyInvoiced(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This estimate cannot be voided because it has already been invoiced.');

        $estimate = new Estimate();
        $estimate->invoice_id = 10;
        $estimate->void();
    }

    public function testVoidAlreadyVoided(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This document has already been voided.');

        $estimate = new Estimate();
        $estimate->voided = true;
        $estimate->void();
    }

    public function testVoid(): void
    {
        self::$voidedEstimate = new Estimate();
        self::$voidedEstimate->setCustomer(self::$customer);
        self::$voidedEstimate->items = [['unit_cost' => 100]];
        self::$voidedEstimate->saveOrFail();

        self::$voidedEstimate->void();

        $this->assertTrue(self::$voidedEstimate->voided);
        $this->assertBetween(time() - self::$voidedEstimate->date_voided, 0, 3);
        $this->assertEquals('voided', self::$voidedEstimate->status);
        $this->assertNull(self::$voidedEstimate->url);
        $this->assertNull(self::$voidedEstimate->pdf_url);
        $this->assertFalse(self::$voidedEstimate->closed);

        // cannot edit once voided
        self::$voidedEstimate->items = [['unit_cost' => 1000]];
        $this->assertFalse(self::$voidedEstimate->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$estimate->id,
            'object' => 'estimate',
            'customer' => self::$customer->id(),
            'name' => 'New Name',
            'currency' => 'eur',
            'items' => [
                [
                    'object' => 'line_item',
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
                            'object' => 'discount',
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
                    'object' => 'line_item',
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
                    'object' => 'line_item',
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
                    'object' => 'discount',
                    'coupon' => self::$coupon->toArray(),
                    'amount' => 5.10,
                    'expires' => null,
                    'from_payment_terms' => false,
                ],
            ],
            'shipping' => [],
            'taxes' => [
                [
                    'object' => 'tax',
                    'tax_rate' => self::$taxRate->toArray(),
                    'amount' => 4.85,
                ],
            ],
            'total' => 101.80,
            'deposit' => 0,
            'deposit_paid' => false,
            'notes' => 'test',
            'purchase_order' => null,
            'number' => 'QUO-001',
            'date' => mktime(0, 0, 0, 6, 12, 2014),
            'payment_terms' => 'NET 30',
            'expiration_date' => null,
            'draft' => false,
            'closed' => false,
            'url' => 'http://invoiced.localhost:1234/estimates/'.self::$company->identifier.'/'.self::$estimate->client_id,
            'pdf_url' => 'http://invoiced.localhost:1234/estimates/'.self::$company->identifier.'/'.self::$estimate->client_id.'/pdf',
            'status' => EstimateStatus::NOT_SENT,
            'approved' => null,
            'approval' => null,
            'invoice' => null,
            'ship_to' => null,
            'metadata' => new stdClass(),
            'created_at' => self::$estimate->created_at,
            'updated_at' => self::$estimate->updated_at,
            'network_document_id' => null,
        ];

        $arr = self::$estimate->toArray();

        // remove item ids
        foreach ($arr['items'] as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            foreach (['discounts', 'taxes'] as $type) {
                foreach ($item[$type] as &$rate) {
                    unset($rate['id']);
                    unset($rate['updated_at']);
                }
            }
        }

        // remove applied rate ids
        foreach (['discounts', 'taxes', 'shipping'] as $type) {
            foreach ($arr[$type] as &$rate) {
                unset($rate['id']);
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
            'number' => 'QUO-001',
            'purchase_order' => null,
            'currency' => 'eur',
            'subtotal' => 107.31,
            'total' => 101.80,
            'deposit' => 0,
            'expiration_date' => null,
            'date' => mktime(0, 0, 0, 6, 12, 2014),
            'payment_terms' => 'NET 30',
            'status' => EstimateStatus::NOT_SENT,
            'metadata' => [],
            '_customer' => self::$customer->id(),
            'customer' => [
                'name' => self::$customer->name,
            ],
        ];

        $this->assertEquals($expected, (new SearchDocumentFactory())->make(self::$estimate));
    }

    /**
     * @depends testCreate
     */
    public function testFindByEstimateNo(): void
    {
        // logout
        self::getService('test.user_context')->set(new User(['id' => -1]));

        // lookup estimate by estimate #
        $estimate = Estimate::where('number', self::$estimate->number)
            ->oneOrNull();

        $this->assertInstanceOf(Estimate::class, $estimate);
        $this->assertEquals(self::$estimate->id(), $estimate->id());
    }

    public function testFindByNonexistentEstimateNo(): void
    {
        // logout
        self::getService('test.user_context')->set(new User(['id' => -1]));

        // lookup non-existent estimate #
        $this->assertNull(Estimate::where('number', 'doesnotexist')->oneOrNull());
    }

    /**
     * @depends testCreate
     */
    public function testEmail(): void
    {
        $emailTemplate = (new DocumentEmailTemplateFactory())->get(self::$estimate);
        self::getService('test.email_spool')->spoolDocument(self::$estimate, $emailTemplate)->flush();

        $this->assertTrue(self::$estimate->sent);
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
            $view = $documentViewTracker->addView(self::$estimate, 'firefox', '10.0.0.1');

            // if the view is a duplicate then it should
            // simply return the past object
            if ($first) {
                $this->assertEquals($first->id(), $view->id());

                continue;
            }

            $first = $view;

            // verify the view object
            $this->assertInstanceOf(DocumentView::class, $view);
            $this->assertEquals('estimate', $view->document_type);
            $this->assertEquals(self::$estimate->id(), $view->document_id);
            $this->assertEquals('firefox', $view->user_agent);
            $this->assertEquals('10.0.0.1', $view->ip);

            // verify the document's status
            $this->assertTrue(self::$estimate->viewed);
            $this->assertEquals(EstimateStatus::VIEWED, self::$estimate->status);

            // verify the event
            self::getService('test.event_spool')->flush(); // write out events
            $event = Event::where('object_type_id', ObjectType::DocumentView->value)
                ->where('object_id', $view)
                ->where('type_id', EventType::EstimateViewed->toInteger())
                ->oneOrNull();

            $this->assertInstanceOf(Event::class, $event);
            $associations = $event->getAssociations();
            $this->assertEquals(self::$estimate->id(), $associations['estimate']);
            $this->assertEquals(self::$customer->id(), $associations['customer']);
            $this->assertNotEquals(false, $event->href);
        }
    }

    /**
     * @depends testCreate
     */
    public function testApprove(): void
    {
        EventSpool::enable();

        $approveEstimate = self::getService('test.approve_estimate');
        $this->assertNull($approveEstimate->approve(self::$estimate, '', '', ''));

        $approveEstimate->approve(self::$estimate, '127.0.0.1', 'user-agent', 'jtk');

        // should create an approved event
        self::getService('test.event_spool')->flush(); // write out events
        $this->assertHasEvent(self::$estimate, EventType::EstimateApproved);

        // should mark the estimate as approved
        $this->assertEquals('JTK', self::$estimate->approved);
        $this->assertEquals(EstimateStatus::APPROVED, self::$estimate->status);
        $this->assertTrue(self::$estimate->closed);

        // should build an approval
        $approval = EstimateApproval::where('estimate_id', self::$estimate->id())->oneOrNull();
        $this->assertInstanceOf(EstimateApproval::class, $approval);
        $this->assertEquals('127.0.0.1', $approval->ip);
        $this->assertEquals('user-agent', $approval->user_agent);
        $this->assertEquals('JTK', $approval->initials);
    }

    /**
     * @depends testCreate
     */
    public function testApproveWithDeposit(): void
    {
        self::$estimate->approved = null;
        self::$estimate->closed = false;
        self::$estimate->deposit = 500;
        self::$estimate->invoice_id = null;
        self::$estimate->saveOrFail();

        self::getService('test.approve_estimate')->approve(self::$estimate, '127.0.0.1', 'user-agent', 'jtk');

        // should mark the estimate as approved
        $this->assertEquals('JTK', self::$estimate->approved);
        $this->assertEquals(EstimateStatus::APPROVED, self::$estimate->status);
        $this->assertFalse(self::$estimate->closed);
        $this->assertFalse(self::$estimate->deposit_paid);
    }

    /**
     * @depends testCreate
     */
    public function testGenerateInvoice(): void
    {
        // test with a closed estimate
        if (!self::$estimate->closed) {
            self::$estimate->closed = true;
            self::$estimate->saveOrFail();
        }

        $invoice = self::getService('test.generate_estimate_invoice')->generateInvoice(self::$estimate);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(self::$estimate->total, $invoice->balance);
        $this->assertEquals('NET 30', $invoice->payment_terms);
        $this->assertFalse($invoice->sent);
        $this->assertEquals('test', $invoice->notes);
        $this->assertGreaterThan(self::$estimate->date, $invoice->date);
        $this->assertEquals(strtotime('+30 days', $invoice->date), $invoice->due_date);

        // verify that invoice line items have different ids from estimate
        $items = self::$estimate->items();
        foreach ($invoice->items() as $k => $item) {
            $this->assertGreaterThan(0, $item['id']);
            $this->assertNotEquals($items[$k]['id'], $item['id']);
        }

        $this->assertEquals($invoice->id(), self::$estimate->invoice);
        $this->assertEquals($invoice->id(), self::$estimate->invoice_id);
        $this->assertEquals(EstimateStatus::INVOICED, self::$estimate->status);
    }

    public function testGenerateInvoiceEarlyDiscount(): void
    {
        $customer = new Customer();
        $customer->name = 'Early Payment';
        $customer->saveOrFail();

        $estimate = new Estimate();
        $estimate->setCustomer($customer);
        $estimate->payment_terms = '2% 10 NET 30';
        $estimate->items = [['unit_cost' => 100]];
        $estimate->saveOrFail();

        $invoice = self::getService('test.generate_estimate_invoice')->generateInvoice($estimate);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals('2% 10 NET 30', $invoice->payment_terms);
        $this->assertEquals(100, $invoice->subtotal);
        $this->assertEquals(98, $invoice->total);

        $discounts = $invoice->discounts();
        $this->assertCount(1, $discounts);
        $this->assertEquals(2, $discounts[0]['amount']);
        $this->assertBetween(strtotime('+10 days') - $discounts[0]['expires'], 0, 3);
    }

    /**
     * @depends testCreate
     */
    public function testRecalculate(): void
    {
        self::$estimate->closed = false;
        self::$estimate->saveOrFail();

        $this->assertTrue(self::$estimate->recalculate());
    }

    /**
     * @depends testCreate
     */
    public function testReorderLineItems(): void
    {
        $items = self::$estimate->items();
        $expectedOrder = [$items[2]['id'], $items[0]['id'], $items[1]['id']];
        $items = [
            $items[2],
            $items[0],
            $items[1], ];
        self::$estimate->items = $items;
        self::$estimate->saveOrFail();

        $newItems = self::$estimate->items(true);
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
        self::$estimate->items = [['quantity' => 8, 'unit_cost' => 100]];
        self::$estimate->saveOrFail();

        $this->assertEquals(800.00, self::$estimate->subtotal);
        $this->assertEquals(798, self::$estimate->total);

        $expected = [
            [
                'object' => 'line_item',
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
        $items = self::$estimate->items();
        unset($items[0]['id']);
        unset($items[0]['created_at']);
        unset($items[0]['updated_at']);
        $this->assertEquals($expected, $items);
    }

    /**
     * @depends testCreate
     */
    public function testEditExistingLineItem(): void
    {
        $items = self::$estimate->items();
        $ids = array_map(function ($item) {
            return $item['id'];
        }, $items);

        $items[0]['unit_cost'] = 700;

        self::$estimate->items = $items;
        self::$estimate->saveOrFail();

        $newIds = array_map(function ($item) {
            return $item['id'];
        }, self::$estimate->items());
        $this->assertEquals($ids, $newIds);
    }

    /**
     * @depends testCreate
     */
    public function testEditNonUnique(): void
    {
        // should not be able to edit estimates with non-unique #
        self::$estimate2->number = 'QUO-001';
        $this->assertFalse(self::$estimate2->save());
    }

    public function testEventMarkedSent(): void
    {
        $estimate = new Estimate();
        $estimate->setCustomer(self::$customer);
        $estimate->items = [['unit_cost' => 100]];
        $estimate->saveOrFail();

        EventSpool::enable();

        $estimate->sent = true;
        $estimate->saveOrFail();

        self::getService('test.event_spool')->flush();

        $events = Event::where('type_id', EventType::EstimateUpdated->toInteger())
            ->where('object_type_id', ObjectType::Estimate->value)
            ->where('object_id', $estimate)
            ->all();
        $storage = self::getService('test.event_storage');

        // look for an event with a marked sent flag
        $markedSentEvent = false;
        foreach ($events as $event) {
            $event->hydrateFromStorage($storage);
            if (property_exists((object) $event->previous, 'status') && EstimateStatus::SENT == $event->object?->status) {
                $markedSentEvent = true;

                break;
            }
        }

        $this->assertTrue($markedSentEvent);
    }

    /**
     * @depends testEditExistingLineItem
     */
    public function testEditRate(): void
    {
        // subtotal at this point is: 5600
        // discounts = 280
        // taxes = 266

        self::$estimate->closed = false;

        $discounts = self::$estimate->discounts();
        $expectedId = $discounts[0]['id'];
        $discounts = [
            [
                'coupon' => 'coupon2',
            ],
            $discounts[0],
        ];

        self::$estimate->discounts = $discounts;
        self::$estimate->saveOrFail();
        $this->assertEquals(5575.5, self::$estimate->total);

        $expected = [
            [
                'amount' => 10,
                'object' => 'discount',
                'coupon' => self::$coupon2->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
            [
                'id' => $expectedId,
                'object' => 'discount',
                'amount' => 280,
                'coupon' => self::$coupon->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
        ];

        $discounts = self::$estimate->discounts();
        unset($discounts[0]['id']);
        unset($discounts[0]['updated_at']);
        unset($discounts[1]['updated_at']);
        $this->assertEquals($expected, $discounts);

        $expected = [
            [
                'amount' => 265.5,
                'object' => 'tax',
                'tax_rate' => self::$taxRate->toArray(),
            ],
        ];

        $taxes = self::$estimate->taxes();
        unset($taxes[0]['id']);
        unset($taxes[0]['updated_at']);
        $this->assertEquals($expected, $taxes);

        $expected = [];
        $this->assertEquals($expected, self::$estimate->shipping());
    }

    /**
     * @depends testEditRate
     */
    public function testDeleteRate(): void
    {
        // subtotal at this point is: 5600
        // discounts = 290
        // taxes = 265.5

        $discounts = self::$estimate->discounts();
        unset($discounts[0]);

        self::$estimate->discounts = $discounts;
        self::$estimate->saveOrFail();
        $this->assertEquals(5586, self::$estimate->total);

        $expected = [
            [
                'object' => 'discount',
                'amount' => 280,
                'coupon' => self::$coupon->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
        ];

        $discounts = self::$estimate->discounts();
        unset($discounts[0]['id']);
        unset($discounts[0]['updated_at']);
        $this->assertEquals($expected, $discounts);

        $expected = [
            [
                'object' => 'tax',
                'amount' => 266,
                'tax_rate' => self::$taxRate->toArray(),
            ],
        ];

        $taxes = self::$estimate->taxes();
        unset($taxes[0]['id']);
        unset($taxes[0]['updated_at']);
        $this->assertEquals($expected, $taxes);

        $expected = [];
        $this->assertEquals($expected, self::$estimate->shipping());

        // delete all rates
        self::$estimate->discounts = [];
        self::$estimate->taxes = [];
        $this->assertTrue(self::$estimate->save());

        $this->assertEquals([], self::$estimate->discounts());
        $this->assertEquals([], self::$estimate->taxes());
        $this->assertEquals([], self::$estimate->shipping());
    }

    /**
     * @depends testCreate
     */
    public function testEditAttachments(): void
    {
        self::$estimate->name = 'Test';
        $this->assertTrue(self::$estimate->save());

        // should keep the attachment
        $n = Attachment::where('parent_type', 'estimate')
            ->where('parent_id', self::$estimate)
            ->where('file_id', self::$file)
            ->count();
        $this->assertEquals(1, $n);

        self::$estimate->attachments = [];
        self::$estimate->saveOrFail();

        // should delete the attachment
        $n = Attachment::where('parent_type', 'estimate')
            ->where('parent_id', self::$estimate)
            ->where('file_id', self::$file)
            ->count();
        $this->assertEquals(0, $n);
    }

    /**
     * @depends testCreate
     */
    public function testMetadata(): void
    {
        $metadata = self::$estimate->metadata;
        $metadata->test = true;
        self::$estimate->metadata = $metadata;
        self::$estimate->saveOrFail();
        $this->assertEquals((object) ['test' => true], self::$estimate->metadata);

        self::$estimate->metadata = (object) ['internal.id' => '12345'];
        self::$estimate->saveOrFail();
        $this->assertEquals((object) ['internal.id' => '12345'], self::$estimate->metadata);

        self::$estimate->metadata = (object) ['array' => [], 'object' => new stdClass()];
        self::$estimate->saveOrFail();
        $this->assertEquals((object) ['array' => [], 'object' => new stdClass()], self::$estimate->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata(): void
    {
        self::$estimate->metadata = (object) [str_pad('', 41) => 'fail'];
        $this->assertFalse(self::$estimate->save());

        self::$estimate->metadata = (object) ['fail' => str_pad('', 256)];
        $this->assertFalse(self::$estimate->save());

        self::$estimate->metadata = (object) array_fill(0, 11, 'fail');
        $this->assertFalse(self::$estimate->save());
        unset(self::$estimate->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        // cannot delete because there is an invoice attached
        $this->assertFalse(self::$estimate->delete());

        self::$estimate->invoice_id = null;
        self::$estimate->saveOrFail();

        EventSpool::enable();
        $this->assertTrue(self::$estimate->delete());
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$estimate, EventType::EstimateDeleted);
    }

    public function testInactiveCustomer(): void
    {
        $estimate = new Estimate();
        $estimate->setCustomer(self::$inactiveCustomer);
        $estimate->items = [['unit_cost' => 100]];
        $this->assertFalse($estimate->save());
        $this->assertEquals('This cannot be created because the customer is inactive', (string) $estimate->getErrors());
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

        $reset = function ($draft) use ($member, $role): Estimate {
            // cleaning the cache
            $member->role = $role->id;
            $member->setRelation('role', $role);
            $estimate = new Estimate();
            $estimate->setCustomer(self::$customer);
            $estimate->draft = $draft;
            $estimate->saveOrFail();

            return $estimate;
        };

        // no permissions
        try {
            $reset(false);
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }

        // issue permissions only
        $role->estimates_create = false;
        $role->estimates_issue = true;
        $role->saveOrFail();
        $reset(false);

        // create new draft
        $role->estimates_edit = true;
        $role->estimates_issue = false;
        $role->saveOrFail();
        $estimate = $reset(true);
        // no issue only
        $estimate->draft = false;
        try {
            $estimate->saveOrFail();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }
        // no issue + edit
        $estimate->name = 'test';
        try {
            $estimate->saveOrFail();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }
        // yes edit only
        $estimate->draft = true;
        $estimate->saveOrFail();

        $role->estimates_edit = true;
        $role->estimates_issue = true;
        $role->saveOrFail();
        // create new  draft credit note
        $estimate = $reset(true);
        // yes issue + edit
        $estimate->draft = true;
        $estimate->name = 'test';
        $estimate->saveOrFail();

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
            self::hasEstimate();
            ACLModelRequester::set($member);
        };

        $reset();
        try {
            self::$estimate->void();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }

        $reset();
        $role->estimates_edit = true;
        $role->saveOrFail();
        try {
            self::$estimate->void();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }

        $reset();
        $role->estimates_edit = false;
        $role->estimates_void = true;
        $role->saveOrFail();
        $member->setRelation('role', $role);
        self::$estimate->void();

        $reset();
        $role->estimates_edit = true;
        $role->saveOrFail();
        self::$estimate->void();

        $member->role = Role::ADMINISTRATOR;
        $member->saveOrFail();
        ACLModelRequester::set($requester);
        $this->assertTrue(true);
    }
}
