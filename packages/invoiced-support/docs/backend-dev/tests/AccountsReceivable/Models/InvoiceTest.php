<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\EmailVariables\InvoiceEmailVariables;
use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Discount;
use App\AccountsReceivable\Models\DocumentView;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\AccountsReceivable\Models\InvoiceDistribution;
use App\AccountsReceivable\Models\ShippingDetail;
use App\AccountsReceivable\Models\Tax;
use App\AccountsReceivable\Operations\SetBadDebt;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\CreditBalance;
use App\CashApplication\Models\CreditBalanceAdjustment;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Chasing\Models\PromiseToPay;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Billing\Models\InvoiceUsageRecord;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Entitlements\Models\Product;
use App\Core\Files\Models\Attachment;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\Core\Search\Libs\SearchDocumentFactory;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentMethod;
use App\SalesTax\Interfaces\TaxCalculatorInterface;
use App\SalesTax\Models\TaxRate;
use App\SalesTax\Models\TaxRule;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Exception;
use Mockery;
use stdClass;

class InvoiceTest extends AppTestCase
{
    private static Invoice $normalInvoice;
    private static Invoice $noDueDateInvoice;
    private static Invoice $overdueInvoice;
    private static Invoice $invoice2;
    private static Invoice $draft;
    private static Invoice $autoDraft;
    private static Coupon $coupon2;
    private static User $ogUser;
    private static Invoice $paymentPlanInvoice;
    private static Invoice $shippingInvoice;
    private static Invoice $voidedInvoice;
    private static ?Model $requester;
    private static Company $company2;
    private static int $invoiceTime;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInactiveCustomer();
        self::hasCoupon();
        self::hasTaxRate();

        self::$customer->taxes = [self::$taxRate->id];
        self::$customer->saveOrFail();

        self::$coupon2 = new Coupon();
        self::$coupon2->create([
            'name' => 'Discount',
            'id' => 'discount2',
            'is_percent' => false,
            'value' => 10, ]);

        self::hasFile();

        self::$ogUser = self::getService('test.user_context')->get();
        self::$requester = ACLModelRequester::get();

        self::getService('test.tenant')->clear();

        self::$company2 = new Company();
        self::$company2->country = 'US';
        self::$company2->name = 'Company 2';
        self::$company2->username = 'company2'.time();
        self::$company2->email = 'test@example.com';
        self::$company2->creator_id = self::getService('test.user_context')->get()->id();
        self::$company2->saveOrFail();
        self::getService('test.product_installer')->install(Product::where('name', 'Accounts Receivable Free')->one(), self::$company2);

        self::$invoiceTime = strtotime('+6 months', (int) gmmktime(0, 0, 0));

        self::getService('test.tenant')->set(self::$company);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        if (isset(self::$company2)) {
            self::$company2->delete();
        }
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
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/test', $invoice->url);
    }

    public function testUrlNoCustomerPortal(): void
    {
        self::$company->features->disable('billing_portal');
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/test', $invoice->url);
        self::$company->features->enable('billing_portal');
    }

    public function testPdfUrl(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/test/pdf', $invoice->pdf_url);
    }

    public function testCsvUrl(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/test/csv', $invoice->csv_url);
    }

    public function testPaymentUrl(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/test/payment', $invoice->payment_url);
    }

    public function testPaymentUrlPaid(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $invoice->paid = true;
        $this->assertNull($invoice->payment_url);
    }

    public function testPaymentUrlClosed(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $invoice->closed = true;
        $this->assertNull($invoice->payment_url);
    }

    public function testPaymentUrlPending(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $invoice->status = Transaction::STATUS_PENDING;
        $this->assertNull($invoice->payment_url);
    }

    public function testPaymentUrlAutomaticCollectionsNoSource(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $invoice->autopay = true;
        $this->assertEquals('http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/test/payment', $invoice->payment_url);
    }

    public function testPaymentUrlAutomaticCollectionsSource(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $invoice->autopay = true;
        $invoice->setCustomer(self::$customer);
        $customer = $invoice->customer();
        $card = new Card();
        $customer->setPaymentSource($card);

        // test with no payment attempts
        $this->assertNull($invoice->payment_url);

        // test with 1 payment attempt and future one scheduled
        $invoice->attempt_count = 1;
        $invoice->next_payment_attempt = time() + 3600;
        $this->assertNull($invoice->payment_url);

        // test with no further payment attempts scheduled
        $invoice->next_payment_attempt = null;
        $this->assertEquals('http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/test/payment', $invoice->payment_url);
    }

    public function testPaymentUrlPaymentPlanSignup(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $invoice->autopay = true;
        $invoice->setCustomer(self::$customer);
        $invoice->payment_plan_id = -2;

        $customer = $invoice->customer();
        $card = new Card();
        $customer->setPaymentSource($card);

        $paymentPlan = new PaymentPlan();
        $paymentPlan->status = PaymentPlan::STATUS_PENDING_SIGNUP;
        $invoice->setRelation('payment_plan_id', $paymentPlan);

        $this->assertNull($invoice->payment_url);
    }

    public function testPaymentUrlPaymentPlanActiveAutoPay(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $invoice->autopay = true;
        $invoice->setCustomer(self::$customer);
        $invoice->payment_plan_id = -2;

        $customer = $invoice->customer();
        $card = new Card();
        $customer->setPaymentSource($card);

        $paymentPlan = new PaymentPlan();
        $paymentPlan->status = PaymentPlan::STATUS_ACTIVE;
        $invoice->setRelation('payment_plan_id', $paymentPlan);

        $this->assertEquals('http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/test/payment', $invoice->payment_url);
    }

    public function testPaymentUrlPaymentPlanActiveNoAutoPay(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->client_id = 'test';
        $invoice->autopay = false;
        $invoice->setCustomer(self::$customer);
        $invoice->payment_plan_id = -2;

        $customer = $invoice->customer();
        $card = new Card();
        $customer->setPaymentSource($card);

        $paymentPlan = new PaymentPlan();
        $paymentPlan->status = PaymentPlan::STATUS_ACTIVE;
        $invoice->setRelation('payment_plan_id', $paymentPlan);

        $this->assertEquals('http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/test/payment', $invoice->payment_url);
    }

    public function testAutopayProperty(): void
    {
        $invoice = new Invoice();
        $invoice->autopay = true;
        $this->assertEquals(AccountsReceivableSettings::COLLECTION_MODE_AUTO, $invoice->collection_mode);
        $invoice->autopay = false;
        $this->assertEquals(AccountsReceivableSettings::COLLECTION_MODE_MANUAL, $invoice->collection_mode);
    }

    public function testDateFormat(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();

        $this->assertEquals(self::$company->date_format, $invoice->dateFormat());
    }

    public function testCurrencyFormat(): void
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'eur';

        $this->assertEquals('€1,125.00', $invoice->currencyFormat(1125));
        $this->assertEquals('€1,125.00', $invoice->currencyFormatHtml(1125));
    }

    public function testGetEmailVariables(): void
    {
        $invoice = new Invoice();
        $this->assertInstanceOf(InvoiceEmailVariables::class, $invoice->getEmailVariables());
    }

    public function testEventAssociations(): void
    {
        $invoice = new Invoice();
        $invoice->customer = 100;
        $invoice->subscription_id = 101;

        $expected = [
            ['customer', 100],
            ['subscription', 101],
        ];

        $this->assertEquals($expected, $invoice->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);

        $expected = array_merge($invoice->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
            'subscription' => null,
            'network_document' => null,
        ]);

        $this->assertEquals($expected, $invoice->getEventObject());
    }

    public function testCannotCreateNegativeTotal(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => -100]];
        $this->assertFalse($invoice->save());
    }

    public function testCreateAutoPayFail(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $errorStack = $invoice->getErrors();

        $this->assertFalse($invoice->create([
            'autopay' => true,
        ]));

        $errors = $errorStack->all();
        $expected = ['Sorry, this business does not support AutoPay. Please enable a supported payment method in Settings > Payments first.'];
        $this->assertEquals($expected, $errors);
    }

    public function testCreateInvalidCustomer(): void
    {
        $invoice = new Invoice();
        $invoice->customer = 12384234;
        $this->assertFalse($invoice->save());
    }

    public function testCreateInvalidSubscription(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->subscription_id = 12384234;
        $this->assertFalse($invoice->save());
    }

    public function testCreateInvalidCurrency(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'au';
        $this->assertFalse($invoice->save());
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        // create a not overdue invoice
        self::$normalInvoice = new Invoice();
        self::$normalInvoice->setCustomer(self::$customer);
        self::$normalInvoice->number = 'INV-001';
        self::$normalInvoice->date = self::$invoiceTime;
        self::$normalInvoice->payment_terms = 'NET 12';
        self::$normalInvoice->items = [
            [
                'quantity' => 1,
                'name' => 'test',
                'unit_cost' => 105.26,
                'discounts' => [
                    [
                        'coupon' => 'coupon',
                    ],
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
        ];
        self::$normalInvoice->amount_paid = 10;
        self::$normalInvoice->discounts = [
            [
                'coupon' => 'coupon',
            ],
        ];
        self::$normalInvoice->taxes = [
            [
                'tax_rate' => 'tax',
            ],
        ];
        self::$normalInvoice->currency = 'eur';
        self::$normalInvoice->notes = 'test';
        self::$normalInvoice->attachments = [self::$file->id()];
        self::$normalInvoice->tags = ['invoice', 'testing_1_2_3', 'invoice'];
        $this->assertTrue(self::$normalInvoice->save());

        // Subtotal: 107.31
        // Line Discounts: 5.263 -> 5.26
        // Subtotal Discounts: 5.10235 -> 5.10
        // Subtotal Tax: 4.8472325 -> 4.85
        // Total: 101.80

        $this->assertEquals(self::$company->id(), self::$normalInvoice->tenant_id);
        $this->assertEquals('INV-001', self::$normalInvoice->number);
        $this->assertEquals(107.31, self::$normalInvoice->subtotal);
        $this->assertEquals(101.80, self::$normalInvoice->total);
        $this->assertEquals(48, strlen(self::$normalInvoice->client_id));
        $this->assertEquals(91.80, self::$normalInvoice->balance);
        $this->assertEquals(['invoice', 'testing_1_2_3'], self::$normalInvoice->tags);

        // should create an attachment
        $n = Attachment::where('parent_type', 'invoice')
            ->where('parent_id', self::$normalInvoice)
            ->where('file_id', self::$file)
            ->count();
        $this->assertEquals(1, $n);

        // should not increment volume
        $this->assertEquals(0, InvoiceUsageRecord::getOrCreate(self::$company, MonthBillingPeriod::now())->count);

        // create an overdue invoice
        self::$overdueInvoice = new Invoice();
        self::$overdueInvoice->setCustomer(self::$customer);
        $this->assertTrue(self::$overdueInvoice->create([
            'date' => time(),
            'due_date' => time() - 3600,
            'chase' => true,
            'items' => [
                [
                    'quantity' => 1,
                    'unit_cost' => 1000,
                ],
            ],
        ]));
        $this->assertEquals('INV-00001', self::$overdueInvoice->number);
        $this->assertEquals(48, strlen(self::$overdueInvoice->client_id));
        $this->assertNotEquals(self::$overdueInvoice->client_id, self::$normalInvoice->client_id);

        $this->assertEquals(1050, self::$overdueInvoice->balance);

        // enable chasing by default
        self::$company->accounts_receivable_settings->chase_new_invoices = true;
        $this->assertTrue(self::$company->accounts_receivable_settings->save());

        // create an invoice with no due date
        self::$noDueDateInvoice = new Invoice();
        self::$noDueDateInvoice->setCustomer(self::$customer);
        $this->assertTrue(self::$noDueDateInvoice->create([
            'items' => [
                [
                    'quantity' => 10,
                    'unit_cost' => 1,
                ],
            ],
        ]));
        $this->assertEquals('INV-00002', self::$noDueDateInvoice->number);
        $this->assertEquals(10.5, self::$noDueDateInvoice->balance);
        $this->assertTrue(self::$noDueDateInvoice->chase);
    }

    /**
     * @depends testCreate
     */
    public function testCreateDraft(): void
    {
        self::$draft = new Invoice();
        self::$draft->setCustomer(self::$customer);
        self::$draft->draft = true;
        $this->assertTrue(self::$draft->save());
    }

    public function testNextInvoiceNumberCollisions(): void
    {
        $sequence = self::$normalInvoice->getNumberingSequence();
        $sequence->setNext(100);

        // create some invoices to test collision prevention
        for ($i = 0; $i < 10; ++$i) {
            $invoice = new Invoice();
            $invoice->setCustomer(self::$customer);
            $invoice->number = 'INV-00'.(100 + $i);
            $this->assertTrue($invoice->save());
        }

        // test next invoice #
        $this->assertEquals(110, $sequence->nextNumber());
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        $invoice = new Invoice();
        $errorStack = $invoice->getErrors();

        $invoice->setCustomer(self::$customer);
        $invoice->number = 'INV-001';
        $this->assertFalse($invoice->save());

        $errors = $errorStack->all();
        $this->assertEquals('The given invoice number has already been taken: INV-001', $errors[0]);
    }

    public function testCreateNewCustomerAndSend(): void
    {
        // create an invoice with a new customer supplied
        // and send it
        self::$invoice2 = new Invoice();
        $this->assertTrue(self::$invoice2->create([
            'customer' => [
                'name' => 'Test Customer',
                'email' => 'new.test@example.com',
            ],
            'send' => true,
            'items' => [
                [
                    'quantity' => 10,
                    'unit_cost' => 1,
                ],
            ],
            'discounts' => [
                [
                    'amount' => 2,
                ],
            ],
        ]));
        self::getService('test.email_spool')->flush();

        $this->assertTrue(self::$invoice2->sent);
        $this->assertEquals(1, Customer::where('email', 'new.test@example.com')->count());
    }

    /**
     * @depends testCreate
     * @depends testCreateDraft
     */
    public function testCreateAutomatic(): void
    {
        self::acceptsCreditCards(TestGateway::ID);

        $auto = new Invoice();
        $auto->setCustomer(self::$customer);
        $auto->autopay = true;
        $auto->items = [['unit_cost' => 100]];
        $this->assertTrue($auto->save());

        // should schedule the next payment attempt
        $this->assertGreaterThan(time(), $auto->next_payment_attempt);

        self::$autoDraft = new Invoice();
        self::$autoDraft->setCustomer(self::$customer);
        self::$autoDraft->autopay = true;
        self::$autoDraft->items = [['unit_cost' => 100]];
        self::$autoDraft->draft = true;
        $this->assertTrue(self::$autoDraft->save());

        // should not schedule the next payment attempt
        $this->assertNull(self::$autoDraft->next_payment_attempt);
    }

    public function testCannotCreateDoubleTax(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100, 'taxes' => [self::$taxRate->id]]];
        $invoice->taxes = [self::$taxRate->id];
        $this->assertFalse($invoice->save());

        $errors = $invoice->getErrors()->all();
        $expected = ['This document cannot be saved because a tax rate (ID: tax) is being applied to a line item and the subtotal. Please make sure the tax rate is applied only once to save this document.'];
        $this->assertEquals($expected, $errors);
    }

    public function testCreateDeduplicateTaxes(): void
    {
        // add a tax rate to the customer
        self::$customer->taxes = [
            self::$taxRate->id,
        ];
        $this->assertTrue(self::$customer->save());

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['name' => 'T', 'unit_cost' => 100]];
        $taxes = [
            [
                'amount' => 100,
                'tax_rate' => self::$taxRate->toArray(),
            ],
        ];
        // trigger a small difference so arrays cannot
        // be considered equal even though the reference the
        // same rate ID
        $taxes[0]['tax_rate']['test'] = true;
        $invoice->taxes = $taxes;
        $this->assertTrue($invoice->save());

        // should de-duplicate the tax rate
        $taxes = $invoice->taxes();
        $this->assertCount(1, $taxes);
        $this->assertEquals(self::$taxRate->id, $taxes[0]['tax_rate']['id']);
    }

    public function testCreateDeduplicateDiscounts(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $discounts = [
            [
                'amount' => 100,
                'coupon' => self::$coupon->toArray(),
            ],
            [
                'amount' => 100,
                'coupon' => self::$coupon->toArray(),
            ],
        ];
        // trigger a small difference so arrays cannot
        // be considered equal even though the reference the
        // same rate ID
        $discounts[0]['coupon']['test'] = true;
        $invoice->discounts = $discounts;
        $this->assertTrue($invoice->save());

        // should de-duplicate the coupon
        $discounts = $invoice->discounts();
        $this->assertCount(1, $discounts);
        $this->assertEquals(self::$coupon->id, $discounts[0]['coupon']['id']);
    }

    public function testCreateShipTo(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->ship_to = [/* @phpstan-ignore-line */
            'name' => 'Test',
            'address1' => '1234 main st',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78735',
            'country' => 'US',
        ];
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();
        self::$shippingInvoice = $invoice;

        $shipping2 = $invoice->ship_to;
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
        $this->assertHasEvent(self::$normalInvoice, EventType::InvoiceCreated);
    }

    public function testCannotVoidPartialPayment(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This invoice cannot be voided because it has a payment applied.');

        $invoice = new Invoice();
        $invoice->amount_paid = 100;
        $invoice->void();
    }

    public function testCannotVoidPendingPayment(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This invoice cannot be voided because it has a pending payment.');

        $invoice = new Invoice();
        $invoice->status = 'pending';
        $invoice->void();
    }

    public function testCannotVoidCredited(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This invoice cannot be voided because it has a credit note applied.');

        $invoice = new Invoice();
        $invoice->amount_credited = 100;
        $invoice->void();
    }

    public function testVoidAlreadyVoided(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This document has already been voided.');

        $invoice = new CreditNote();
        $invoice->voided = true;
        $invoice->void();
    }

    public function testVoid(): void
    {
        self::$voidedInvoice = new Invoice();
        self::$voidedInvoice->setCustomer(self::$customer);
        self::$voidedInvoice->items = [['unit_cost' => 100]];
        self::$voidedInvoice->saveOrFail();

        self::$voidedInvoice->void();

        $this->assertTrue(self::$voidedInvoice->voided);
        $this->assertBetween(time() - self::$voidedInvoice->date_voided, 0, 3);
        $this->assertEquals('voided', self::$voidedInvoice->status);
        $this->assertNull(self::$voidedInvoice->url);
        $this->assertNull(self::$voidedInvoice->pdf_url);
        $this->assertNull(self::$voidedInvoice->csv_url);
        $this->assertEquals(0, self::$voidedInvoice->balance);
        $this->assertFalse(self::$voidedInvoice->paid);
        $this->assertNull(self::$voidedInvoice->date_paid);
        $this->assertFalse(self::$voidedInvoice->closed);

        // cannot edit once voided
        self::$voidedInvoice->items = [['unit_cost' => 1000]];
        $this->assertFalse(self::$voidedInvoice->save());
    }

    /**
     * @depends testCreate
     * @depends testVoid
     */
    public function testQuery(): void
    {
        $invoices = Invoice::all();

        $this->assertCount(21, $invoices);

        // look for our known invoices
        $find = [
            self::$normalInvoice->id(),
            self::$noDueDateInvoice->id(),
            self::$overdueInvoice->id(),
            self::$invoice2->id(),
            self::$draft->id(),
            self::$autoDraft->id(),
            self::$voidedInvoice->id(),
        ];
        foreach ($invoices as $invoice) {
            if (false !== ($key = array_search($invoice->id(), $find))) {
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

        $this->assertEquals(0, Invoice::count());

        // update the customer territory
        self::$customer->metadata = (object) ['territory' => 'Texas'];
        self::$customer->saveOrFail();
        self::$normalInvoice->setRelation('customer', self::$customer);

        $invoices = Invoice::all();
        $this->assertCount(20, $invoices);
        $this->assertEquals(self::$normalInvoice->id(), $invoices[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testFindClientId(): void
    {
        $this->assertNull(Invoice::findClientId(''));
        $this->assertNull(Invoice::findClientId('1234'));

        $this->assertEquals(self::$normalInvoice->id(), Invoice::findClientId(self::$normalInvoice->client_id)->id()); /* @phpstan-ignore-line */

        $old = self::$normalInvoice->client_id;
        self::$normalInvoice->refreshClientId();
        $this->assertNotEquals($old, self::$normalInvoice->client_id);

        // set client ID in the past
        self::$normalInvoice->refreshClientId(false, strtotime('-1 year'));
        /** @var Invoice $obj */
        $obj = Invoice::findClientId(self::$normalInvoice->client_id);

        // set the client ID to expire soon
        self::$normalInvoice->refreshClientId(false, strtotime('+29 days'));
        /** @var Invoice $obj */
        $obj = Invoice::findClientId(self::$normalInvoice->client_id);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$normalInvoice->id(),
            'object' => 'invoice',
            'customer' => self::$customer->id(),
            'name' => 'Invoice',
            'currency' => 'eur',
            'items' => [
                [
                    'catalog_item' => null,
                    'quantity' => 1,
                    'name' => 'test',
                    'description' => '',
                    'unit_cost' => 105.26,
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
            'notes' => 'test',
            'number' => 'INV-001',
            'date' => self::$invoiceTime,
            'payment_terms' => 'NET 12',
            'due_date' => strtotime('+12 days', self::$invoiceTime),
            'url' => 'http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/'.self::$normalInvoice->client_id,
            'payment_url' => 'http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/'.self::$normalInvoice->client_id.'/payment',
            'pdf_url' => 'http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/'.self::$normalInvoice->client_id.'/pdf',
            'csv_url' => 'http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/'.self::$normalInvoice->client_id.'/csv',
            'status' => InvoiceStatus::NotSent->value,
            'draft' => false,
            'closed' => false,
            'paid' => false,
            'chase' => false,
            'balance' => 91.80,
            'purchase_order' => null,
            'payment_plan' => null,
            'next_chase_on' => null,
            'needs_attention' => false,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => 0,
            'next_payment_attempt' => null,
            'ship_to' => null,
            'metadata' => new stdClass(),
            'created_at' => self::$normalInvoice->created_at,
            'updated_at' => self::$normalInvoice->updated_at,
            'late_fees' => true,
            'network_document_id' => null,
            'subscription_id' => null,
        ];

        $arr = self::$normalInvoice->toArray();

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
            'name' => 'Invoice',
            'number' => 'INV-001',
            'purchase_order' => null,
            'currency' => 'eur',
            'subtotal' => 107.31,
            'total' => 101.80,
            'date' => self::$invoiceTime,
            'payment_terms' => 'NET 12',
            'due_date' => strtotime('+12 days', self::$invoiceTime),
            'status' => InvoiceStatus::NotSent->value,
            'balance' => 91.80,
            'autopay' => false,
            'attempt_count' => 0,
            'next_payment_attempt' => null,
            'metadata' => [],
            '_customer' => self::$customer->id(),
            'customer' => [
                'name' => self::$customer->name,
            ],
        ];

        $this->assertEquals($expected, (new SearchDocumentFactory())->make(self::$normalInvoice));
    }

    /**
     * @depends testCreate
     */
    public function testToArrayHook(): void
    {
        $expected = [
            'customerName' => self::$customer->name,
            'items' => [
                [
                    'catalog_item' => null,
                    'quantity' => 1,
                    'name' => 'test',
                    'description' => '',
                    'unit_cost' => 105.26,
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
        ];
        $arr = [];
        self::$normalInvoice->toArrayHook($arr, [], ['customerName' => true], []);

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
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$normalInvoice->name = 'New Name';
        $this->assertTrue(self::$normalInvoice->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        self::getService('test.event_spool')->flush(); // write out events

        $this->assertEquals(1, Event::where('type_id', EventType::InvoiceUpdated->toInteger())
            ->where('object_type_id', ObjectType::Invoice->value)
            ->where('object_id', self::$normalInvoice)
            ->count());
    }

    /**
     * @depends testCreate
     */
    public function testEventMarkedSent(): void
    {
        self::$normalInvoice->sent = false;
        $this->assertTrue(self::$normalInvoice->save());

        EventSpool::enable();

        self::$normalInvoice->sent = true;
        $this->assertTrue(self::$normalInvoice->save());

        self::getService('test.event_spool')->flush(); // write out events
        $events = Event::where('type_id', EventType::InvoiceUpdated->toInteger())
            ->where('object_type_id', ObjectType::Invoice->value)
            ->where('object_id', self::$normalInvoice)
            ->all();
        $storage = self::getService('test.event_storage');

        // look for an event with a marked sent flag
        $markedSentEvent = false;
        foreach ($events as $event) {
            $event->hydrateFromStorage($storage);
            if (property_exists((object) $event->previous, 'status') && InvoiceStatus::Sent->value == $event->object?->status) {
                $markedSentEvent = true;

                break;
            }
        }

        $this->assertTrue($markedSentEvent);
    }

    /**
     * @depends testCreate
     */
    public function testEditNonUnique(): void
    {
        // should not be able to edit invoices with non-unique #
        self::$overdueInvoice->number = 'INV-001';
        $this->assertFalse(self::$overdueInvoice->save());
        self::$overdueInvoice->clearCache();
    }

    /**
     * @depends testCreate
     */
    public function testEditEmptyCurrency(): void
    {
        // should not be able to clear invoice currency
        self::$normalInvoice->currency = '';
        $this->assertFalse(self::$normalInvoice->save());
        self::$normalInvoice->clearCache();
    }

    /**
     * @depends testEdit
     */
    public function testSetNegativeAmountPaid(): void
    {
        self::$normalInvoice->amount_paid = -10834;
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals(0, self::$normalInvoice->amount_paid);
    }

    public function testEditTotalBlockOverpayment(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';
        $invoice->items = [['unit_cost' => 5]];
        $invoice->calculate_taxes = false;
        $invoice->saveOrFail();

        $invoice->amount_paid = 100;
        $invoice->saveOrFail();
        $this->assertEquals(-95, $invoice->balance);
    }

    /**
     * @depends testCreate
     */
    public function testEditNextChase(): void
    {
        self::$normalInvoice->recalculate_chase = true;
        $this->assertTrue(self::$normalInvoice->save());

        $t = strtotime('+1 year');
        self::$normalInvoice->next_chase_on = $t;
        self::$normalInvoice->next_chase_step = 'email';
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals($t, self::$normalInvoice->next_chase_on);
        $this->assertEquals('email', self::$normalInvoice->next_chase_step);
        $this->assertFalse(self::$normalInvoice->recalculate_chase);
    }

    /**
     * @depends testCreate
     */
    public function testEditAutoPayNoDueDate(): void
    {
        self::$autoDraft->due_date = time();
        $this->assertTrue(self::$autoDraft->save());
        // should allow due date to be set
        $this->assertNotNull(self::$autoDraft->due_date);
    }

    /**
     * @depends testCreateDraft
     */
    public function testIssue(): void
    {
        self::$draft->draft = false;
        $this->assertTrue(self::$draft->save());
    }

    /**
     * @depends testCreateAutomatic
     */
    public function testIssueAutomatic(): void
    {
        // issue AutoPay invoice
        self::$autoDraft->draft = false;
        $this->assertTrue(self::$autoDraft->save());

        // should schedule the next payment attempt
        $this->assertGreaterThan(time(), self::$autoDraft->next_payment_attempt);
    }

    public function testCannotEditCustomer(): void
    {
        $invoice = new Invoice(['id' => -100, 'customer' => -1, 'tenant_id' => self::$company->id()]);
        $invoice->customer = -2;
        $this->assertFalse($invoice->save());
        $this->assertEquals(['Invalid request parameter `customer`. The customer cannot be modified.'], $invoice->getErrors()->all());
    }

    /**
     * @depends testCreateShipTo
     */
    public function testEditShipTo(): void
    {
        // change the address
        self::$shippingInvoice->ship_to = [/* @phpstan-ignore-line */
            'name' => 'Test',
            'address1' => '5301 southwest parkway',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78735',
            'country' => 'US',
        ];
        self::$shippingInvoice->saveOrFail();

        $shipping2 = self::$shippingInvoice->ship_to;
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
        self::$shippingInvoice->ship_to = null;
        self::$shippingInvoice->saveOrFail();

        $this->assertNull(self::$shippingInvoice->ship_to);
    }

    /**
     * @depends testIssueAutomatic
     */
    public function testPendingAutomatic(): void
    {
        $pendingTxn = new Transaction();
        $pendingTxn->status = Transaction::STATUS_PENDING;
        $pendingTxn->setInvoice(self::$autoDraft);
        $pendingTxn->amount = self::$autoDraft->balance;
        $this->assertTrue($pendingTxn->save());

        // should cancel any scheduled payment attempts
        $this->assertEquals(InvoiceStatus::Pending->value, self::$autoDraft->refresh()->status);
        $this->assertNull(self::$autoDraft->next_payment_attempt);
    }

    /**
     * @depends testCreate
     */
    public function testFindByInvoiceNo(): void
    {
        // logout
        self::getService('test.user_context')->set(new User(['id' => -1]));

        // lookup invoice by invoice #
        $invoice = Invoice::where('number', self::$normalInvoice->number)->oneOrNull();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(self::$normalInvoice->id(), $invoice->id());
    }

    public function testFindByNonexistentInvoiceNo(): void
    {
        // logout
        self::getService('test.user_context')->set(new User(['id' => -1]));

        // lookup non-existent invoice #
        $invoice = Invoice::where('number', 'doesnotexist')->oneOrNull();

        $this->assertNull($invoice);
    }

    public function testEmail(): void
    {
        // unmark sent
        self::$normalInvoice->sent = false;
        self::$normalInvoice->saveOrFail();

        // send it
        $emailTemplate = (new DocumentEmailTemplateFactory())->get(self::$normalInvoice);
        self::getService('test.email_spool')->spoolDocument(self::$normalInvoice, $emailTemplate)->flush();
        $this->assertTrue(self::$normalInvoice->sent);

        // apply a payment to invoice
        self::$normalInvoice->amount_paid = self::$normalInvoice->total;
        self::$normalInvoice->saveOrFail();

        // resend it
        $emailTemplate = (new DocumentEmailTemplateFactory())->get(self::$normalInvoice);
        self::getService('test.email_spool')->spoolDocument(self::$normalInvoice, $emailTemplate, [['email' => 'something_else@example.com']])->flush();

        self::$normalInvoice->closed = false;
        self::$normalInvoice->amount_paid = 0;
        self::$normalInvoice->saveOrFail();

        // send an invoice reminder
        $emailTemplate = (new DocumentEmailTemplateFactory())->get(self::$normalInvoice);
        self::getService('test.email_spool')->spoolDocument(self::$normalInvoice, $emailTemplate)->flush();
    }

    public function testAddView(): void
    {
        EventSpool::enable();

        $documentViewTracker = self::getService('test.document_view_tracker');
        self::$normalInvoice->closed = false;

        // views should not be duplicated for the same
        // document/user agent/ip combo
        $first = false;
        for ($i = 0; $i < 5; ++$i) {
            $view = $documentViewTracker->addView(self::$normalInvoice, 'firefox', '10.0.0.1');

            // if the view is a duplicate then it should
            // simply return the past object
            if ($first) {
                $this->assertEquals($first->id(), $view->id());

                continue;
            }

            $first = $view;

            // verify the view object
            $this->assertInstanceOf(DocumentView::class, $view);
            $this->assertEquals('invoice', $view->document_type);
            $this->assertEquals(self::$normalInvoice->id(), $view->document_id);
            $this->assertEquals('firefox', $view->user_agent);
            $this->assertEquals('10.0.0.1', $view->ip);

            // verify the document's status
            $this->assertTrue(self::$normalInvoice->viewed);
            $this->assertEquals(InvoiceStatus::Viewed->value, self::$normalInvoice->status);

            // verify the event
            self::getService('test.event_spool')->flush(); // write out events
            $event = Event::where('object_type_id', ObjectType::DocumentView->value)
                ->where('object_id', $view)
                ->where('type_id', EventType::InvoiceViewed->toInteger())
                ->oneOrNull();

            $this->assertInstanceOf(Event::class, $event);
            $associations = $event->getAssociations();
            $this->assertEquals(self::$normalInvoice->id(), $associations['invoice']);
            $this->assertEquals(self::$customer->id(), $associations['customer']);
            $this->assertNotEquals(false, $event->href);
        }
    }

    /**
     * @depends testCreate
     */
    public function testEventPaid(): void
    {
        self::getService('test.database')->delete('Events', ['object_id' => self::$normalInvoice->id()]);

        // unmark invoice as paid
        self::$normalInvoice->amount_paid = 0;
        self::$normalInvoice->closed = false;
        $this->assertTrue(self::$normalInvoice->save());

        EventSpool::enable();

        // mark it as paid
        self::$normalInvoice->amount_paid = self::$normalInvoice->balance;
        $this->assertTrue(self::$normalInvoice->save());

        self::getService('test.email_spool')->flush();
        self::getService('test.event_spool')->flush(); // write out events

        // should create an invoice.paid event
        $paidEvents = Event::where('type_id', EventType::InvoicePaid->toInteger())
            ->where('object_type_id', ObjectType::Invoice->value)
            ->where('object_id', self::$normalInvoice)
            ->all();

        $this->assertCount(1, $paidEvents);

        // should NOT create an invoice.updated event where marked paid
        $updateEvents = Event::where('type_id', EventType::InvoiceUpdated->toInteger())
            ->where('object_type_id', ObjectType::Invoice->value)
            ->where('object_id', self::$normalInvoice)
            ->all();
        $storage = self::getService('test.event_storage');

        $hasMarkedPaidUpdate = false;
        foreach ($updateEvents as $event) {
            $event->hydrateFromStorage($storage);
            if (property_exists((object) $event->previous, 'paid') && !$event->previous?->paid) {
                $hasMarkedPaidUpdate = true;

                break;
            }
        }
        $this->assertFalse($hasMarkedPaidUpdate, 'Found an invoice.updated event that went from unpaid -> paid');
    }

    /**
     * @depends testCreate
     */
    public function testEventPaidAfterUpdate(): void
    {
        self::getService('test.database')->delete('Events', ['object_id' => self::$normalInvoice->id()]);

        EventSpool::enable();

        // unmark invoice as paid / trigger an updated event
        self::$normalInvoice->amount_paid = 0;
        self::$normalInvoice->sent = false;
        self::$normalInvoice->closed = false;
        $this->assertTrue(self::$normalInvoice->save());

        // mark it as paid
        self::$normalInvoice->amount_paid = self::$normalInvoice->balance;
        $this->assertTrue(self::$normalInvoice->save());

        // trigger another update
        self::$normalInvoice->closed = false;
        self::$normalInvoice->sent = true;
        $this->assertTrue(self::$normalInvoice->save());

        self::getService('test.email_spool')->flush();
        self::getService('test.event_spool')->flush(); // write out events

        // should create an invoice.paid paid event
        $paidEvents = Event::where('type_id', EventType::InvoicePaid->toInteger())
            ->where('object_type_id', ObjectType::Invoice->value)
            ->where('object_id', self::$normalInvoice)
            ->all();

        $this->assertCount(1, $paidEvents);

        // should NOT create an invoice.updated event
        $updateEvents = Event::where('type_id', EventType::InvoiceUpdated->toInteger())
            ->where('object_type_id', ObjectType::Invoice->value)
            ->where('object_id', self::$normalInvoice)
            ->count();
        $this->assertEquals(0, $updateEvents);
    }

    /**
     * @depends testCreate
     */
    public function testEventPaidWithThankYou(): void
    {
        self::getService('test.database')->delete('Events', ['object_id' => self::$normalInvoice->id()]);

        // enable thank you email
        self::$customer->email = 'test@example.com';
        $this->assertTrue(self::$customer->save());

        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = EmailTemplate::PAID_INVOICE;
        $emailTemplate->subject = 'subject';
        $emailTemplate->body = 'test';
        $options = $emailTemplate->options;
        $options[EmailTemplateOption::SEND_ONCE_PAID] = 1;
        $emailTemplate->options = $options;
        $emailTemplate->save();

        // unmark the invoice as paid or sent
        self::$normalInvoice->customer()->refresh();
        self::$normalInvoice->amount_paid = 0;
        self::$normalInvoice->sent = false;
        $this->assertTrue(self::$normalInvoice->save());

        EventSpool::enable();

        // mark it as paid
        self::$normalInvoice->amount_paid = self::$normalInvoice->balance;
        $this->assertTrue(self::$normalInvoice->save());

        // send any spooled emails
        self::getService('test.email_spool')->flush();

        // should mark the invoice as sent
        $this->assertTrue(self::$normalInvoice->sent);

        self::getService('test.event_spool')->flush(); // write out events

        // should create an invoice.paid paid event
        $paidEvents = Event::where('type_id', EventType::InvoicePaid->toInteger())
            ->where('object_type_id', ObjectType::Invoice->value)
            ->where('object_id', self::$normalInvoice)
            ->all();

        $this->assertCount(1, $paidEvents);

        // should NOT create an invoice.updated event
        $updateEvents = Event::where('type_id', EventType::InvoiceUpdated->toInteger())
            ->where('object_type_id', ObjectType::Invoice->value)
            ->where('object_id', self::$normalInvoice)
            ->count();
        $this->assertEquals(0, $updateEvents);
    }

    /**
     * @depends testCreate
     */
    public function testDatePaid(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $this->assertTrue($invoice->save());

        // apply a payment
        $payment1 = new Transaction();
        $payment1->setInvoice($invoice);
        $payment1->date = strtotime('+3 days');
        $payment1->amount = $invoice->total;
        $this->assertTrue($payment1->save());

        $this->assertEquals($payment1->date, $invoice->refresh()->date_paid);

        // refund it
        $refund = new Transaction();
        $refund->setParentTransaction($payment1);
        $refund->type = Transaction::TYPE_REFUND;
        $refund->setInvoice($invoice);
        $refund->date = strtotime('+3 days');
        $refund->amount = $invoice->total;
        $this->assertTrue($refund->save());

        $this->assertNull($invoice->refresh()->date_paid);

        // apply a partial payment
        $payment2 = new Transaction();
        $payment2->setInvoice($invoice);
        $payment2->date = strtotime('+7 days');
        $payment2->amount = 100;
        $this->assertTrue($payment2->save());

        $this->assertNull($invoice->refresh()->date_paid);

        // apply another payment
        $payment3 = new Transaction();
        $payment3->setInvoice($invoice);
        $payment3->date = strtotime('+5 days');
        $payment3->amount = $invoice->balance;
        $this->assertTrue($payment3->save());
        $this->assertEquals($payment2->date, $invoice->refresh()->date_paid);
    }

    /**
     * @depends testCreate
     */
    public function testDateBadDebt(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->closed = true;
        $invoice->saveOrFail();
        $this->assertEquals(InvoiceStatus::NotSent->value, $invoice->refresh()->status);
        $this->assertNull($invoice->date_bad_debt);

        $badDebt = new SetBadDebt();
        $invoice = $badDebt->set($invoice);
        $this->assertEquals(InvoiceStatus::BadDebt->value, $invoice->refresh()->status);
        $this->assertLessThan(3, abs(time() - $invoice->date_bad_debt));

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->closed = true;
        $invoice->date_bad_debt = time();
        $invoice->saveOrFail();
        $this->assertEquals(InvoiceStatus::BadDebt->value, $invoice->refresh()->status);
        $this->assertLessThan(3, abs(time() - $invoice->date_bad_debt));

        // re-open it
        $invoice->closed = false;
        $invoice->saveOrFail();

        $this->assertNotEquals(InvoiceStatus::BadDebt->value, $invoice->refresh()->status);
        $this->assertNotNull($invoice->date_bad_debt);

        // reset bad debt date
        $invoice->date_bad_debt = null;
        $invoice->saveOrFail();

        // close it again
        $invoice->closed = true;
        $invoice->date_bad_debt = time();
        $invoice->saveOrFail();

        $this->assertEquals(InvoiceStatus::BadDebt->value, $invoice->refresh()->status);
        $this->assertLessThan(3, abs(time() - $invoice->date_bad_debt));

        // reopen and apply payment
        $invoice->closed = false;
        $invoice->saveOrFail();

        // apply a payment
        $payment = new Transaction();
        $payment->setInvoice($invoice);
        $payment->amount = $invoice->balance;
        $this->assertTrue($payment->save());

        $this->assertNotNull($invoice->refresh()->date_bad_debt);
    }

    /**
     * @depends testCreate
     */
    public function testReorderLineItems(): void
    {
        self::$normalInvoice->closed = false;
        self::$normalInvoice->amount_paid = 0;

        $items = self::$normalInvoice->items();
        $expectedOrder = [$items[2]['id'], $items[0]['id'], $items[1]['id']];
        $items = [
            $items[2],
            $items[0],
            $items[1], ];
        self::$normalInvoice->items = $items;
        $this->assertTrue(self::$normalInvoice->save());

        $newItems = self::$normalInvoice->items(true);
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
        self::$normalInvoice->items = [['quantity' => 8, 'unit_cost' => 100]];
        self::$normalInvoice->save();

        $this->assertEquals(800.00, self::$normalInvoice->subtotal);
        $this->assertEquals(798, self::$normalInvoice->total);
        $this->assertEquals(798, self::$normalInvoice->balance);

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
        $items = self::$normalInvoice->items();
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
        $items = self::$normalInvoice->items();
        $ids = array_map(function ($item) {
            return $item['id'];
        }, $items);

        $items[0]['unit_cost'] = 700;

        // and add a new line item
        $items[] = ['name' => 'Test'];

        self::$normalInvoice->items = $items;
        $this->assertTrue(self::$normalInvoice->save());

        $items2 = self::$normalInvoice->items(true);
        $this->assertCount(2, $items2);

        // check that all of the old ids exist
        foreach ($items2 as $item) {
            $index = array_search($item['id'], $ids);
            if (false !== $index) {
                unset($ids[$index]);
            }
        }

        $this->assertCount(0, $ids);
    }

    /**
     * @depends testCreate
     */
    public function testRecalculate(): void
    {
        $this->assertTrue(self::$normalInvoice->recalculate());
    }

    /**
     * @depends testEditExistingLineItem
     */
    public function testEditRate(): void
    {
        // subtotal at this point is: 5600
        // discounts = 280
        // taxes = 266

        $discounts = self::$normalInvoice->discounts();
        $expectedId = $discounts[0]['id'];
        $discounts = [
            [
                'coupon' => 'discount2',
            ],
            $discounts[0],
        ];

        self::$normalInvoice->discounts = $discounts;
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals(5575.5, self::$normalInvoice->total);

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

        $discounts = self::$normalInvoice->discounts();
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

        $taxes = self::$normalInvoice->taxes();
        unset($taxes[0]['id']);
        unset($taxes[0]['object']);
        unset($taxes[0]['updated_at']);
        $this->assertEquals($expected, $taxes);

        $expected = [];
        $this->assertEquals($expected, self::$normalInvoice->shipping());
    }

    /**
     * @depends testEditRate
     */
    public function testDeleteRate(): void
    {
        // subtotal at this point is: 5600
        // discounts = 290
        // taxes = 265.5

        $discounts = self::$normalInvoice->discounts();
        unset($discounts[0]);

        self::$normalInvoice->discounts = $discounts;
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals(5586, self::$normalInvoice->total);

        $expected = [
            [
                'amount' => 280,
                'coupon' => self::$coupon->toArray(),
                'expires' => null,
                'from_payment_terms' => false,
            ],
        ];

        $discounts = self::$normalInvoice->discounts();
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

        $taxes = self::$normalInvoice->taxes();
        unset($taxes[0]['id']);
        unset($taxes[0]['object']);
        unset($taxes[0]['updated_at']);
        $this->assertEquals($expected, $taxes);

        $expected = [];
        $this->assertEquals($expected, self::$normalInvoice->shipping());

        // delete all rates
        self::$normalInvoice->discounts = [];
        self::$normalInvoice->taxes = [];
        $this->assertTrue(self::$normalInvoice->save());

        $this->assertEquals([], self::$normalInvoice->discounts());
        $this->assertEquals([], self::$normalInvoice->taxes());
        $this->assertEquals([], self::$normalInvoice->shipping());
    }

    /**
     * @depends testCreate
     */
    public function testEditDate(): void
    {
        self::$normalInvoice->date = 100;
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals(100 + 12 * 86400, self::$normalInvoice->due_date);
    }

    /**
     * @depends testCreate
     */
    public function testEditPaymentTerms(): void
    {
        self::$normalInvoice->payment_terms = 'NET 14';
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals(100 + 14 * 86400, self::$normalInvoice->due_date);
    }

    /**
     * @depends testCreate
     */
    public function testEditAttachments(): void
    {
        self::$normalInvoice->name = 'Test';
        $this->assertTrue(self::$normalInvoice->save());

        // should keep the attachment
        $n = Attachment::where('parent_type', 'invoice')
            ->where('parent_id', self::$normalInvoice)
            ->where('file_id', self::$file)
            ->count();
        $this->assertEquals(1, $n);

        self::$normalInvoice->attachments = [];
        $this->assertTrue(self::$normalInvoice->save());

        // should delete the attachment
        $n = Attachment::where('parent_type', 'invoice')
            ->where('parent_id', self::$normalInvoice)
            ->where('file_id', self::$file)
            ->count();
        $this->assertEquals(0, $n);
    }

    /**
     * @depends testCreate
     */
    public function testSetExpectedPaymentDate(): void
    {
        self::$normalInvoice->expected_payment_date = [
            'date' => 1440553865,
            'method' => PaymentMethod::CHECK,
        ];
        $this->assertTrue(self::$normalInvoice->save());

        /** @var PromiseToPay $promiseToPay */
        $promiseToPay = PromiseToPay::where('invoice_id', self::$normalInvoice)->oneOrNull();
        $this->assertInstanceOf(PromiseToPay::class, $promiseToPay);

        $expected = [
            'amount' => 5600.0,
            'broken' => false,
            'created_at' => $promiseToPay->created_at,
            'currency' => 'eur',
            'customer_id' => self::$customer->id,
            'date' => 1440553865,
            'id' => $promiseToPay->id(),
            'invoice_id' => self::$normalInvoice->id,
            'kept' => false,
            'method' => PaymentMethod::CHECK,
            'reference' => null,
            'updated_at' => $promiseToPay->updated_at,
        ];

        $this->assertEquals($expected, $promiseToPay->toArray());
        $this->assertEquals($expected, self::$normalInvoice->expected_payment_date);

        // update the method
        self::$normalInvoice->expected_payment_date = ['method' => PaymentMethod::WIRE_TRANSFER];
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals(PaymentMethod::WIRE_TRANSFER, $promiseToPay->refresh()->method);
    }

    /**
     * @depends testCreate
     */
    public function testEditTags(): void
    {
        self::$normalInvoice->tags = [];
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals([], self::$normalInvoice->tags);

        self::$normalInvoice->tags = ['test', 'saving', 'tags', 'tags'];
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals(['test', 'saving', 'tags'], self::$normalInvoice->tags);

        self::$normalInvoice->tags = ['test', 'saving', 'tags', 'tags'];
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals(['test', 'saving', 'tags'], self::$normalInvoice->tags);
    }

    /**
     * @depends testCreate
     */
    public function testMetadata(): void
    {
        $metadata = self::$normalInvoice->metadata;
        $metadata->test = true;
        self::$normalInvoice->metadata = $metadata;
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals((object) ['test' => true], self::$normalInvoice->metadata);

        self::$normalInvoice->metadata = (object) ['internal.id' => '12345'];
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals((object) ['internal.id' => '12345'], self::$normalInvoice->metadata);

        self::$normalInvoice->metadata = (object) ['array' => [], 'object' => new stdClass()];
        $this->assertTrue(self::$normalInvoice->save());
        $this->assertEquals((object) ['array' => [], 'object' => new stdClass()], self::$normalInvoice->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata(): void
    {
        self::$normalInvoice->metadata = (object) [str_pad('', 41) => 'fail'];
        $this->assertFalse(self::$normalInvoice->save());

        self::$normalInvoice->metadata = (object) ['fail' => str_pad('', 256)];
        $this->assertFalse(self::$normalInvoice->save());

        self::$normalInvoice->metadata = (object) array_fill(0, 11, 'fail');
        $this->assertFalse(self::$normalInvoice->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        EventSpool::enable();

        $this->assertTrue(self::$normalInvoice->delete());

        $deleteModels = [Transaction::class];

        foreach ($deleteModels as $m) {
            $this->assertEquals(0, $m::where('invoice', self::$normalInvoice->id())->count());
        }
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$normalInvoice, EventType::InvoiceDeleted);
    }

    public function testGetDefaultEmailContacts(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 350]];
        $invoice->saveOrFail();

        $expected = [
            [
                'name' => 'Sherlock',
                'email' => 'test@example.com',
            ],
        ];

        $this->assertEquals($expected, $invoice->getDefaultEmailContacts());

        // enable invoice distributions
        self::$company->features->enable('invoice_distributions');
        $this->assertEquals($expected, $invoice->getDefaultEmailContacts());

        // create a distribution for the invoice
        $distribution = new InvoiceDistribution();
        $distribution->invoice_id = (int) $invoice->id();
        $distribution->department = 'test_department';
        $distribution->saveOrFail();

        $expected = [];
        $this->assertEquals($expected, $invoice->getDefaultEmailContacts());

        $contact = new Contact();
        $contact->customer = self::$customer;
        $contact->email = 'distro@example.com';
        $contact->name = 'Test Distro';
        $contact->department = 'test_department';
        $contact->saveOrFail();

        $expected = [
            [
                'name' => 'Test Distro',
                'email' => 'distro@example.com',
            ],
        ];

        $this->assertEquals($expected, $invoice->getDefaultEmailContacts());
    }

    public function testAutoApplyCredits(): void
    {
        self::$company->accounts_receivable_settings->auto_apply_credits = true;
        self::$company->accounts_receivable_settings->saveOrFail();

        // create a new credit
        $credit = new CreditBalanceAdjustment();
        $credit->setCustomer(self::$customer);
        $credit->amount = 105;
        $this->assertTrue($credit->save());

        // create a new invoice
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        // invoice should be paid
        $this->assertTrue($invoice->paid);
        $this->assertEquals(105, $invoice->amount_paid);

        // should create balance charge from previous credits
        $n = Transaction::where('customer', self::$customer->id())
            ->where('invoice', $invoice)
            ->where('type', Transaction::TYPE_CHARGE)
            ->where('method', PaymentMethod::BALANCE)
            ->where('amount', 105)
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testAutoApplyCreditsDifferentCurrency(): void
    {
        // create a new credit
        $credit = new CreditBalanceAdjustment();
        $credit->setCustomer(self::$customer);
        $credit->currency = 'eur';
        $credit->amount = 105;
        $credit->saveOrFail();

        // create a new invoice
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'eur';
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        // invoice should be paid
        $this->assertTrue($invoice->paid);
        $this->assertEquals(105, $invoice->amount_paid);

        // should create balance charge from previous credits
        $n = Transaction::where('customer', self::$customer->id())
            ->where('invoice', $invoice)
            ->where('type', Transaction::TYPE_CHARGE)
            ->where('method', PaymentMethod::BALANCE)
            ->where('currency', 'eur')
            ->where('amount', 105)
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testAutoApplyCreditsCreditNote(): void
    {
        // create a new credit note
        $creditNote = self::getTestDataFactory()->createCreditNote(self::$customer);

        // create a new invoice
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        // invoice should be paid
        $this->assertTrue($invoice->paid);
        $this->assertEquals(105, $invoice->amount_credited);
        $this->assertEquals(0, $invoice->balance);

        // should fully apply credit note
        $this->assertEquals(0, $creditNote->refresh()->balance);
    }

    public function testAttachPaymentPlan(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 1000]];
        $invoice->attempt_count = 1;
        $this->assertTrue($invoice->save());
        self::$paymentPlanInvoice = $invoice;

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('+2 days');
        $installment1->amount = 750;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+1 month');
        $installment2->amount = 300;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
        ];

        // INVD-251 test
        $discount = new Discount();
        $discount->expires = time();
        $invoice->discounts = [$discount];
        $this->assertFalse($invoice->attachPaymentPlan($paymentPlan, true, true));
        $this->assertEquals("Payment plan can't be applied to an invoice with discount with an expiration date.", $invoice->getErrors()->all()[0]);
        $invoice->discounts = [];

        $this->assertTrue($invoice->attachPaymentPlan($paymentPlan, true, true));
        // verify changes to payment plan
        $this->assertGreaterThan(0, $paymentPlan->id());
        $this->assertEquals($paymentPlan->id(), $invoice->payment_plan_id);
        $this->assertEquals($installment2->date + 86400, $invoice->due_date);

        // verify changes to invoice
        $this->assertTrue($invoice->autopay);
        $this->assertEquals('Payment Plan', $invoice->payment_terms);
        $this->assertEquals(PaymentPlan::STATUS_PENDING_SIGNUP, $paymentPlan->status);
        $this->assertNull($invoice->next_payment_attempt);
        $this->assertEquals(0, $invoice->attempt_count);
    }

    /**
     * @depends testAttachPaymentPlan
     */
    public function testApplyPaymentWithPaymentPlan(): void
    {
        // apply a $100 payment
        self::$paymentPlanInvoice->amount_paid = 100;
        self::$paymentPlanInvoice->saveOrFail();

        // the payment plan installment should be updated
        $this->assertEquals(650, self::$paymentPlanInvoice->paymentPlan()->installments[0]->balance); /* @phpstan-ignore-line */

        // apply an $800 payment
        self::$paymentPlanInvoice->amount_paid = 800;
        self::$paymentPlanInvoice->saveOrFail();

        // the payment plan installment should be updated
        $this->assertEquals(0, self::$paymentPlanInvoice->paymentPlan()->installments[0]->balance); /* @phpstan-ignore-line */
        $this->assertEquals(250, self::$paymentPlanInvoice->paymentPlan()->installments[1]->balance); /* @phpstan-ignore-line */
    }

    /**
     * @depends testAttachPaymentPlan
     * @depends testApplyPaymentWithPaymentPlan
     */
    public function testEditPaymentPlan(): void
    {
        /** @var PaymentPlan $oldPaymentPlan */
        $oldPaymentPlan = self::$paymentPlanInvoice->paymentPlan();
        $oldPaymentPlan->status = PaymentPlan::STATUS_ACTIVE;
        $oldPaymentPlan->saveOrFail();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('+2 days');
        $installment1->amount = 750;
        $installment1->balance = 0;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+1 month');
        $installment2->amount = 300;
        $installment2->balance = 250;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
        ];

        $this->assertTrue(self::$paymentPlanInvoice->attachPaymentPlan($paymentPlan, true, false));

        // verify changes to payment plan
        $this->assertGreaterThan(0, $paymentPlan->id());
        $this->assertEquals($paymentPlan->id(), self::$paymentPlanInvoice->payment_plan_id);
        $this->assertEquals($installment2->date + 86400, self::$paymentPlanInvoice->due_date);

        // verify changes to invoice
        $this->assertTrue(self::$paymentPlanInvoice->autopay);
        $this->assertEquals('Payment Plan', self::$paymentPlanInvoice->payment_terms);
        $this->assertEquals(PaymentPlan::STATUS_ACTIVE, $paymentPlan->status);
        $this->assertEquals($installment2->date, self::$paymentPlanInvoice->next_payment_attempt);
        $this->assertEquals(0, self::$paymentPlanInvoice->attempt_count);
    }

    /**
     * @depends testEditPaymentPlan
     */
    public function testCannotChangeTotalWithPaymentPlan(): void
    {
        self::$paymentPlanInvoice->items = [['unit_cost' => self::$paymentPlanInvoice->total - 0.01]];
        $this->assertFalse(self::$paymentPlanInvoice->save());

        $this->assertEquals('The invoice total cannot be modified when there is an active payment plan attached. Please remove the payment plan before modifying the invoice.', self::$paymentPlanInvoice->getErrors()->all()[0]);
    }

    public function testAttachPaymentPlanNoAutoPay(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 1000]];
        $invoice->attempt_count = 1;
        $this->assertTrue($invoice->save());
        self::$paymentPlanInvoice = $invoice;

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('+2 days');
        $installment1->amount = 750;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+1 month');
        $installment2->amount = 300;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
        ];

        $this->assertTrue($invoice->attachPaymentPlan($paymentPlan, false, true));

        // verify changes to payment plan
        $this->assertGreaterThan(0, $paymentPlan->id());
        $this->assertEquals($paymentPlan->id(), $invoice->payment_plan_id);
        $this->assertEquals($installment2->date + 86400, $invoice->due_date);

        // verify changes to invoice
        $this->assertFalse($invoice->autopay);
        $this->assertEquals('Payment Plan', $invoice->payment_terms);
        $this->assertEquals(PaymentPlan::STATUS_ACTIVE, $paymentPlan->status);
        $this->assertNull($invoice->next_payment_attempt);
        $this->assertEquals(0, $invoice->attempt_count);
    }

    public function testDisableAutoPayPaymentPlanInvoice(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 1000]];
        $invoice->attempt_count = 1;
        $invoice->saveOrFail();
        self::$paymentPlanInvoice = $invoice;

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('+2 days');
        $installment1->amount = 750;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+1 month');
        $installment2->amount = 300;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
        ];

        $this->assertTrue($invoice->attachPaymentPlan($paymentPlan, true, true));
        $this->assertEquals(PaymentPlan::STATUS_PENDING_SIGNUP, $paymentPlan->refresh()->status);

        $invoice->autopay = false;
        $invoice->saveOrFail();
        $this->assertEquals(PaymentPlan::STATUS_ACTIVE, $paymentPlan->refresh()->status);
    }

    public function testCannotAddPendingForExisting(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can only call withPending() for new invoices.');
        $invoice = new Invoice(['id' => 100]);
        $invoice->withPending()->save();
    }

    public function testCannotInvoicePendingWithoutItems(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to create an invoice because the customer does not have any pending line items.');

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->withPending()->save();
    }

    public function testInvoicePendingLineItems(): void
    {
        self::$customer->taxes = [];
        self::$customer->saveOrFail();

        // create pending line items
        $line = new PendingLineItem();
        $line->setParent(self::$customer);
        $line->name = 'Line 1';
        $line->unit_cost = 100;
        $line->saveOrFail();

        $line2 = new PendingLineItem();
        $line2->setParent(self::$customer);
        $line2->name = 'Line 2';
        $line2->quantity = 2;
        $line2->unit_cost = 150;
        $line2->saveOrFail();

        // subscription line item
        self::hasPlan();
        $subscription = self::getService('test.create_subscription')
            ->create([
                'customer' => self::$customer,
                'plan' => self::$plan,
                'taxes' => [self::$taxRate->id],
                'discounts' => [self::$coupon->id],
                'start_date' => strtotime('+14 days'),
            ]);
        $line3 = new PendingLineItem();
        $line3->setParent(self::$customer);
        $line3->name = 'Line 3';
        $line3->quantity = 3;
        $line3->unit_cost = 200;
        $line3->subscription_id = (int) $subscription->id();
        $line3->saveOrFail();

        // create invoice
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $this->assertTrue($invoice->withPending(true)->save());

        $expected = [
            [
                'type' => null,
                'name' => 'Line 1',
                'description' => null,
                'quantity' => 1.0,
                'unit_cost' => 100.0,
                'amount' => 100.0,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
            [
                'type' => null,
                'name' => 'Line 2',
                'description' => null,
                'quantity' => 2.0,
                'unit_cost' => 150.0,
                'amount' => 300.0,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
            [
                'type' => null,
                'name' => 'Line 3',
                'description' => null,
                'quantity' => 3.0,
                'unit_cost' => 200.0,
                'amount' => 600.0,
                'catalog_item' => null,
                'subscription' => $subscription->id(),
                'prorated' => false,
                'period_start' => null,
                'period_end' => null,
                'discountable' => true,
                'discounts' => [
                    [
                        'coupon' => self::$coupon->toArray(),
                        'amount' => 30.0,
                        'expires' => null,
                        'from_payment_terms' => false,
                    ],
                ],
                'taxable' => true,
                'taxes' => [
                    [
                        'tax_rate' => self::$taxRate->toArray(),
                        'amount' => 28.5,
                    ],
                ],
                'metadata' => new stdClass(),
            ],
        ];

        $items = $invoice->items();
        foreach ($items as &$item) {
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

        $this->assertCount(3, $items);
        $this->assertEquals($expected, $items);

        $this->assertEquals(998.5, $invoice->total);

        // pending line items should no longer exist
        $this->assertNull(PendingLineItem::find($line->id()));
        $this->assertNull(PendingLineItem::find($line2->id()));
        $this->assertNull(PendingLineItem::find($line3->id()));
    }

    public function testInvoicePendingLineItemsCreditNote(): void
    {
        // create pending line items
        $line = new PendingLineItem();
        $line->setParent(self::$customer);
        $line->name = 'Line 1';
        $line->unit_cost = 150;
        $this->assertTrue($line->save());

        $line2 = new PendingLineItem();
        $line2->setParent(self::$customer);
        $line2->name = 'Line 2';
        $line2->quantity = 2;
        $line2->unit_cost = -200;
        $this->assertTrue($line2->save());

        // create invoice
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $this->assertTrue($invoice->withPending()->save());

        $expected = [
            [
                'type' => null,
                'name' => 'Line 1',
                'description' => null,
                'quantity' => 1.0,
                'unit_cost' => 150.0,
                'amount' => 150.0,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
        ];

        $items = $invoice->items();
        foreach ($items as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
        }

        $this->assertEquals($expected, $items);

        $this->assertEquals(150.0, $invoice->total);
        $this->assertTrue($invoice->paid);

        // should create a credit note
        $creditNote = CreditNote::where('customer', self::$customer->id())
            ->sort('id desc')
            ->oneOrNull();

        $this->assertInstanceOf(CreditNote::class, $creditNote);

        $expected = [
            [
                'type' => null,
                'name' => 'Line 2',
                'description' => null,
                'quantity' => 2.0,
                'unit_cost' => 200.0,
                'amount' => 400.0,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
        ];

        $items = $creditNote->items();
        foreach ($items as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
        }

        $this->assertEquals($expected, $items);

        $this->assertEquals(400.0, $creditNote->total);
        $this->assertTrue($creditNote->paid);

        // should issue a credit for the remaining balance
        $credits = Transaction::where('credit_note_id', $creditNote->id)->all();
        $credit = $credits[0];
        $this->assertInstanceOf(Transaction::class, $credit);
        $this->assertEquals(Transaction::TYPE_ADJUSTMENT, $credit->type);
        $this->assertEquals(-150.0, $credit->amount);

        $credit = $credits[1];
        $this->assertInstanceOf(Transaction::class, $credit);
        $this->assertEquals(Transaction::TYPE_ADJUSTMENT, $credit->type);
        $this->assertEquals(-250.0, $credit->amount);

        // pending line items should no longer exist
        $this->assertNull(PendingLineItem::find($line->id()));
        $this->assertNull(PendingLineItem::find($line2->id()));
    }

    public function testInvoicePendingLineItemsCreditNoteWithCredits(): void
    {
        // zero out credits on customer's account
        $adjustment = new CreditBalanceAdjustment();
        $adjustment->setCustomer(self::$customer);
        $adjustment->amount = -CreditBalance::lookup(self::$customer)->toDecimal();
        $adjustment->saveOrFail();

        // add credits to the customer's account
        $credits = new CreditBalanceAdjustment();
        $credits->setCustomer(self::$customer);
        $credits->amount = 50;
        $credits->saveOrFail();

        // create pending line items
        $line = new PendingLineItem();
        $line->setParent(self::$customer);
        $line->name = 'Line 1';
        $line->unit_cost = 150;
        $line->taxable = false;
        $this->assertTrue($line->save());

        $line2 = new PendingLineItem();
        $line2->setParent(self::$customer);
        $line2->name = 'Line 2';
        $line2->unit_cost = -100;
        $line2->taxable = false;
        $this->assertTrue($line2->save());

        // create invoice
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $this->assertTrue($invoice->withPending()->save());

        $expected = [
            [
                'type' => null,
                'name' => 'Line 1',
                'description' => null,
                'quantity' => 1.0,
                'unit_cost' => 150.0,
                'amount' => 150.0,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => false,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
        ];

        $items = $invoice->items();
        foreach ($items as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
        }

        $this->assertEquals($expected, $items);

        $this->assertEquals(150, $invoice->total);
        $this->assertTrue($invoice->paid);

        // should create a credit note
        $creditNote = CreditNote::where('customer', self::$customer->id())
            ->sort('id desc')
            ->oneOrNull();

        $this->assertInstanceOf(CreditNote::class, $creditNote);

        $expected = [
            [
                'type' => null,
                'name' => 'Line 2',
                'description' => null,
                'quantity' => 1.0,
                'unit_cost' => 100.0,
                'amount' => 100.0,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => false,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
        ];

        $items = $creditNote->items();
        foreach ($items as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
        }

        $this->assertEquals($expected, $items);

        $this->assertEquals(100.0, $creditNote->total);
        $this->assertTrue($creditNote->paid);

        // pending line items should no longer exist
        $this->assertNull(PendingLineItem::find($line->id()));
        $this->assertNull(PendingLineItem::find($line2->id()));
    }

    public function testInvoicePendingLineItemsDraftCreditNote(): void
    {
        // create pending line items
        $line = new PendingLineItem();
        $line->setParent(self::$customer);
        $line->name = 'Line 1';
        $line->unit_cost = 150;
        $this->assertTrue($line->save());

        $line2 = new PendingLineItem();
        $line2->setParent(self::$customer);
        $line2->name = 'Line 2';
        $line2->quantity = 2;
        $line2->unit_cost = -200;
        $this->assertTrue($line2->save());

        // create invoice
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->draft = true;
        $this->assertTrue($invoice->withPending()->save());

        $expected = [
            [
                'type' => null,
                'name' => 'Line 1',
                'description' => null,
                'quantity' => 1.0,
                'unit_cost' => 150.0,
                'amount' => 150.0,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
        ];

        $items = $invoice->items();
        foreach ($items as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
        }

        $this->assertEquals($expected, $items);

        $this->assertEquals(150.0, $invoice->total);
        $this->assertFalse($invoice->paid);

        // should create a draft credit note
        $creditNote = CreditNote::where('customer', self::$customer->id())
            ->sort('id desc')
            ->oneOrNull();

        $this->assertInstanceOf(CreditNote::class, $creditNote);
        $this->assertTrue($creditNote->draft);
        // should not apply the credit note
        $this->assertFalse($creditNote->paid);
    }

    public function testInvoicePendingLineItemsSubscription(): void
    {
        // pending line item unrelated to subscriptions which should
        // be returned from the first `withPending()` calls.
        $line = new PendingLineItem();
        $line->setParent(self::$customer);
        $line->name = 'Line 1';
        $line->unit_cost = 100;
        $line->saveOrFail();

        // subscription 1
        $subscription1 = self::getService('test.create_subscription')
            ->create([
                'customer' => self::$customer,
                'plan' => self::$plan,
                'taxes' => [self::$taxRate->id],
                'discounts' => [self::$coupon->id],
                'start_date' => strtotime('+14 days'),
            ]);

        $sub1PendingLine = new PendingLineItem();
        $sub1PendingLine->setParent(self::$customer);
        $sub1PendingLine->name = 'Subscription 1 Pending Line Item';
        $sub1PendingLine->quantity = 3;
        $sub1PendingLine->unit_cost = 200;
        $sub1PendingLine->subscription_id = (int) $subscription1->id();
        $sub1PendingLine->saveOrFail();

        $invoice1 = new Invoice();
        $invoice1->subscription_id = (int) $subscription1->id();
        $invoice1->setCustomer(self::$customer);
        $this->assertTrue($invoice1->withPending(true)->save());

        // NOTE: Subscription2's pending line items
        // should not be included in the `withPending()` result
        // for invoices that aren't associated with Subscription2.
        //
        // E.g. $invoice1->withPending() should not include
        // $sub2PendingLine in $invoice1's line items.

        // subscription 2
        $subscription2 = self::getService('test.create_subscription')
            ->create([
                'customer' => self::$customer,
                'plan' => self::$plan,
                'taxes' => [self::$taxRate->id],
                'discounts' => [self::$coupon->id],
                'start_date' => strtotime('+14 days'),
            ]);

        $sub2PendingLine = new PendingLineItem();
        $sub2PendingLine->setParent(self::$customer);
        $sub2PendingLine->name = 'Subscription 2 Pending Line Item';
        $sub2PendingLine->quantity = 3;
        $sub2PendingLine->unit_cost = 200;
        $sub2PendingLine->subscription_id = (int) $subscription2->id();
        $sub2PendingLine->saveOrFail();

        // items should not include subscription 2's pending line item
        $expected = [
            [
                'type' => null,
                'name' => 'Line 1',
                'description' => null,
                'quantity' => 1.0,
                'unit_cost' => 100.0,
                'amount' => 100.0,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
            [
                'type' => null,
                'name' => 'Subscription 1 Pending Line Item',
                'description' => null,
                'quantity' => 3.0,
                'unit_cost' => 200.0,
                'amount' => 600.0,
                'catalog_item' => null,
                'subscription' => $subscription1->id(),
                'prorated' => false,
                'period_start' => null,
                'period_end' => null,
                'discountable' => true,
                'discounts' => [
                    [
                        'coupon' => self::$coupon->toArray(),
                        'amount' => 30.0,
                        'expires' => null,
                        'from_payment_terms' => false,
                    ],
                ],
                'taxable' => true,
                'taxes' => [
                    [
                        'tax_rate' => self::$taxRate->toArray(),
                        'amount' => 28.5,
                    ],
                ],
                'metadata' => new stdClass(),
            ],
        ];

        // items for subscription 1 invoice
        $items1 = $invoice1->items();
        foreach ($items1 as &$item) {
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

        $this->assertCount(2, $items1);
        $this->assertEquals($expected, $items1);
        $this->assertEquals(698.5, $invoice1->total);

        // pending line items should no longer exist
        $this->assertNull(PendingLineItem::find($line->id()));
        $this->assertNull(PendingLineItem::find($sub1PendingLine->id()));

        // subscription 2's pending line item should've never been
        // added to an invoice so it should still exist.
        /** @var PendingLineItem $sub2PendingLineItem */
        $sub2PendingLineItem = PendingLineItem::find($sub2PendingLine->id());
        $this->assertNotNull($sub2PendingLineItem);
        $this->assertTrue($sub2PendingLineItem->delete()); // delete for future tests
    }

    public function testCannotChangeTotalPendingInvoice(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();

        $this->assertEquals(200, $invoice->balance);

        $transaction = new Transaction();
        $transaction->status = Transaction::STATUS_PENDING;
        $transaction->setInvoice($invoice);
        $transaction->setRelation('invoice', $invoice);
        $transaction->amount = 100;
        $transaction->saveOrFail();

        $invoice->updateStatus();
        $this->assertEquals(InvoiceStatus::Pending->value, $invoice->status);

        // should be able to modify the invoice as long
        // as it does not change the total
        $invoice->sent = true;
        $this->assertTrue($invoice->save());

        // should not be able to modify the total
        $invoice->items = [['unit_cost' => 100]];
        $this->assertFalse($invoice->save());

        // clearing the payment should allow us to update the total
        $transaction->status = Transaction::STATUS_SUCCEEDED;
        $transaction->save();
        $invoice->items = [['unit_cost' => 100]];
        $this->assertTrue($invoice->save());
    }

    public function testConcurrencyIssues(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $invoice2 = Invoice::findOrFail($invoice->id());

        $invoice->amount_paid = 100;
        $invoice->saveOrFail();

        $invoice2->chase = false;
        $invoice2->saveOrFail();

        $this->assertEquals(0, $invoice->refresh()->balance);
        $this->assertEquals(100, $invoice->amount_paid);
    }

    public function testTaxAssessment(): void
    {
        $taxRule = new TaxRule();
        $taxRule->tax_rate = self::$taxRate->id;
        $taxRule->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $this->assertCount(1, $invoice->taxes);
        $this->assertEquals(105.0, $invoice->total);

        // create a new invoice with tax assessment disabled

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->calculate_taxes = false;
        $invoice->saveOrFail();

        $this->assertCount(0, $invoice->taxes);
        $this->assertEquals(100.0, $invoice->total);
    }

    public function testEditWithTaxes(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->tax = 1; /* @phpstan-ignore-line */
        $invoice->calculate_taxes = false;
        $invoice->saveOrFail();

        $this->assertCount(1, $invoice->taxes);
        $this->assertEquals(101.0, $invoice->total);

        $invoice->tax = 2; /* @phpstan-ignore-line */
        $invoice->saveOrFail();
        $this->assertCount(1, $invoice->taxes);
        $this->assertEquals(102.0, $invoice->total);
    }

    public function testCreateLineItemsMultitenant(): void
    {
        $id = self::$invoice2->items()[0]['id'];

        self::getService('test.tenant')->set(self::$company2);

        $customer = new Customer();
        $customer->name = 'Test';
        $this->assertTrue($customer->save());

        // create an invoice that references line items from another company
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [
            [
                'id' => $id,
            ],
        ];

        // creating should fail
        $this->assertFalse($invoice->save());

        $this->assertEquals("Referenced line item that does not exist: $id", $invoice->getErrors()[0]['message']);
    }

    public function testCreateAppliedRatesMultitenant(): void
    {
        $id = self::$invoice2->discounts()[0]['id'];

        self::getService('test.tenant')->set(self::$company2);

        $customer = new Customer();
        $customer->name = 'Test';
        $this->assertTrue($customer->save());

        // create an invoice that references applied rates from another company
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->discounts = [
            [
                'id' => $id,
                'amount' => 10,
            ],
        ];

        // creating should fail
        $this->assertFalse($invoice->save());

        $this->assertEquals("Referenced discount that does not exist: $id", $invoice->getErrors()[0]['message']);
    }

    public function testUpdateLineItemsMultitenant(): void
    {
        $id = self::$invoice2->items()[0]['id'];

        self::getService('test.tenant')->set(self::$company2);

        $customer = new Customer();
        $customer->name = 'Test';
        $this->assertTrue($customer->save());

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $this->assertTrue($invoice->save());

        // update an invoice that references line items from another company
        $invoice->closed = false;
        $invoice->items = [
            [
                'id' => $id,
            ],
        ];

        // creating should fail
        $this->assertFalse($invoice->save());

        $this->assertEquals("Referenced line item that does not exist: $id", $invoice->getErrors()[0]['message']);
    }

    public function testUpdateAppliedRatesMultitenant(): void
    {
        $id = self::$invoice2->discounts()[0]['id'];

        self::getService('test.tenant')->set(self::$company2);

        $customer = new Customer();
        $customer->name = 'Test';
        $this->assertTrue($customer->save());

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $this->assertTrue($invoice->save());

        // update an invoice that references line items from another company
        $invoice->discounts = [
            [
                'id' => $id,
                'amount' => 10,
            ],
        ];

        // creating should fail
        $this->assertFalse($invoice->save());

        $this->assertEquals("Referenced discount that does not exist: $id", $invoice->getErrors()[0]['message']);
    }

    //
    // Credit Limits
    //

    public function testCannotCreateCreditLimit(): void
    {
        $customer = new Customer();
        $customer->name = 'Credit Limit';
        $customer->credit_limit = 100;
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100.01]];
        $this->assertFalse($invoice->save());

        // verify the error
        $this->assertEquals('This invoice cannot be created because the new amount outstanding (100.01 USD) exceeds the account\'s credit limit (100 USD).', $invoice->getErrors()->all()[0]);

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $this->assertTrue($invoice->save());

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 1]];
        $this->assertFalse($invoice->save());

        // verify the error
        $this->assertEquals('This invoice cannot be created because the new amount outstanding (101 USD) exceeds the account\'s credit limit (100 USD).', $invoice->getErrors()->all()[0]);
    }

    public function testCannotCreateCreditHold(): void
    {
        $customer = new Customer();
        $customer->name = 'Credit Hold';
        $customer->credit_hold = true;
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $this->assertFalse($invoice->save());

        // verify the error
        $this->assertEquals('New invoices cannot be created for this account because it has a credit hold.', $invoice->getErrors()->all()[0]);
    }

    public function testInvd165(): void
    {
        self::$company2->accounts_receivable_settings->auto_apply_credits = true;
        self::$company2->accounts_receivable_settings->saveOrFail();

        // Tests adding a credit, deleting the invoice, and making
        // sure that the applied credits are removed
        $customer = new Customer();
        $customer->name = 'INVD-165';
        $customer->saveOrFail();

        $credit = new CreditBalanceAdjustment();
        $credit->setCustomer($customer);
        $credit->amount = 500;
        $credit->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $this->assertFalse($invoice->delete());
    }

    public function testUnnecessaryTaxEvent(): void
    {
        EventSpool::enable();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->taxes = [['amount' => 1]];
        $invoice->saveOrFail();

        self::getService('test.event_spool')->flush(); // write out events

        $invoice->items = [
            [
                'id' => $invoice->items()[0]['id'],
                'unit_cost' => 100,
            ],
        ];
        $invoice->taxes = [
            [
                'id' => $invoice->taxes()[0]['id'],
                'amount' => 1,
            ],
        ];
        $invoice->saveOrFail();

        self::getService('test.event_spool')->flush(); // write out events

        // should create invoice.created
        $this->assertHasEvent($invoice, EventType::InvoiceCreated);

        // should not create invoice.updated because nothing changed
        $this->assertHasEvent($invoice, EventType::InvoiceUpdated, 0);
    }

    public function testInvd2270(): void
    {
        // Tests that the invoice # is set before sales tax calculation happens.
        $taxRate = new TaxRate();
        $taxRate->name = 'Sales Tax';
        $taxRate->value = 5;
        $taxRate->saveOrFail();

        $calculator = Mockery::mock(TaxCalculatorInterface::class);
        $calculator->shouldReceive('assess')
            ->andReturnUsing(function (SalesTaxInvoice $salesTaxInvoice) use ($taxRate) {
                $this->assertStringStartsWith('INV-', (string) $salesTaxInvoice->getNumber());

                return Tax::expandList([$taxRate->id]);
            })
            ->once();

        $customer = new Customer();
        $customer->name = 'Sales Tax';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setTaxCalculator($calculator);
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $taxes = $invoice->taxes();
        $this->assertCount(1, $taxes);
        $this->assertEquals($taxRate->id, $taxes[0]['tax_rate']['id']);
    }

    public function testDraftPaymentApplication(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->draft = true;
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 100;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 100,
            ],
        ];
        $payment->saveOrFail();
        $this->assertFalse($invoice->draft);
    }

    public function testDraftCreditNoteApplication(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->draft = true;
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

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
                'document_type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 100,
            ],
        ];
        $payment->saveOrFail();
        $this->assertFalse($invoice->draft);
    }

    public function testInactiveCustomer(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$inactiveCustomer);
        $invoice->items = [['unit_cost' => 100]];
        $this->assertFalse($invoice->save());
        $this->assertEquals('This cannot be created because the customer is inactive', (string) $invoice->getErrors());
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

        $reset = function ($draft) use ($member, $role): Invoice {
            // cleaning the cache
            $member->role = $role->id;
            $member->setRelation('role', $role);
            $invoice = new Invoice();
            $invoice->setCustomer(self::$customer);
            $invoice->draft = $draft;
            $invoice->saveOrFail();

            return $invoice;
        };

        // no permissions
        try {
            $reset(false);
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }

        // issue permissions only
        $role->invoices_create = false;
        $role->invoices_issue = true;
        $role->saveOrFail();
        $reset(false);

        $role->invoices_edit = true;
        $role->invoices_issue = false;
        $role->saveOrFail();
        // create new  draft credit note
        $invoice = $reset(true);
        // no issue only
        $invoice->draft = false;
        try {
            $invoice->saveOrFail();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }
        // no issue + edit
        $invoice->name = 'test';
        try {
            $invoice->saveOrFail();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }
        // yes edit only
        $invoice->draft = true;
        $invoice->saveOrFail();

        $role->invoices_edit = true;
        $role->invoices_issue = true;
        $role->saveOrFail();
        // create new  draft credit note
        $invoice = $reset(true);
        // yes issue + edit
        $invoice->draft = true;
        $invoice->name = 'test';
        $invoice->saveOrFail();

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
            self::hasInvoice();
            ACLModelRequester::set($member);
        };

        $reset();
        try {
            self::$invoice->void();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException) {
        }

        $reset();
        $role->invoices_edit = true;
        $role->saveOrFail();
        try {
            self::$invoice->void();
            $this->assertTrue(false, 'No exception thrown');
        } catch (ModelException $e) {
        }

        $reset();
        $role->invoices_edit = false;
        $role->invoices_void = true;
        $role->saveOrFail();
        $member->setRelation('role', $role);
        self::$invoice->void();

        $reset();
        $role->invoices_edit = true;
        $role->saveOrFail();
        self::$invoice->void();

        $member->role = Role::ADMINISTRATOR;
        $member->saveOrFail();
        ACLModelRequester::set($requester);
        $this->assertTrue(true);
    }

    public function testCreateInvoiceDelivery(): void
    {
        self::getService('test.tenant')->set(self::$company);

        self::hasInvoice();
        $invoice = self::$invoice;

        $invoice->createInvoiceDelivery([
            'emails' => 'test@test.com',
        ]);
        /** @var InvoiceDelivery $delivery */
        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id)->one();
        $this->assertEquals(null, $delivery->cadence_id);
        $this->assertEquals([], $delivery->chase_schedule);
        $this->assertEquals('test@test.com', $delivery->emails);

        $cadence1 = new InvoiceChasingCadence();
        $cadence1->name = 'Chasing Cadence';
        $cadence1->default = false;
        $cadence1->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => 3,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];
        $cadence1->saveOrFail();

        $cadence = new InvoiceChasingCadence();
        $cadence->name = 'Chasing Cadence';
        $cadence->default = true;
        $cadence->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => 4,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];
        $cadence->saveOrFail();

        $invoice->createInvoiceDelivery([
            'emails' => 'test2@test.com',
        ]);
        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id)->one();
        $this->assertEquals(null, $delivery->cadence_id);
        $this->assertEquals([], $delivery->chase_schedule);
        $this->assertEquals('test2@test.com', $delivery->emails);

        $invoice->createInvoiceDelivery([
            'emails' => 'test2@test.com',
            'cadence_id' => $cadence1->id,
        ]);
        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id)->one();
        $this->assertEquals($cadence1->id, $delivery->cadence_id);
        $this->assertEquals([
            'hour' => 3,
            'email' => true,
            'sms' => false,
            'letter' => false,
        ], $delivery->chase_schedule[0]['options']);
        $this->assertEquals('test2@test.com', $delivery->emails);

        $invoice->createInvoiceDelivery([
            'emails' => 'test2@test.com',
            'cadence_id' => $cadence1->id,
            'chase_schedule' => [
                [
                    'trigger' => InvoiceChasingCadence::ON_ISSUE,
                    'options' => [
                        'hour' => 2,
                        'email' => true,
                        'sms' => false,
                        'letter' => false,
                    ],
                ],
            ],
        ]);
        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id)->one();
        $this->assertEquals(null, $delivery->cadence_id);
        $this->assertEquals([
            'hour' => 2,
            'email' => true,
            'sms' => false,
            'letter' => false,
        ], $delivery->chase_schedule[0]['options']);
        $this->assertEquals('test2@test.com', $delivery->emails);

        self::hasInvoice();
        $invoice = self::$invoice;
        $invoice->createInvoiceDelivery([
            'emails' => 'test@test.com',
        ]);
        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id)->one();
        $this->assertEquals($cadence->id, $delivery->cadence_id);
        $this->assertEquals([
                        'hour' => 4,
                        'email' => true,
                        'sms' => false,
                        'letter' => false,
                    ], $delivery->chase_schedule[0]['options']);
        $this->assertEquals('test@test.com', $delivery->emails);

        self::hasInvoice();
        $invoice = self::$invoice;
        InvoiceDelivery::where('invoice_id', $invoice->id)->delete();
        $invoice->createInvoiceDelivery([]);
        $this->assertNull(InvoiceDelivery::where('invoice_id', $invoice->id)->oneOrNull());
        $invoice->createInvoiceDelivery(null);
        $this->assertNull(InvoiceDelivery::where('invoice_id', $invoice->id)->oneOrNull());
        $invoice->closed = true;
        $invoice->saveOrFail();
        $invoice->createInvoiceDelivery([
            'emails' => 'test@test.com',
        ]);
        $this->assertNull(InvoiceDelivery::where('invoice_id', $invoice->id)->oneOrNull());
    }

    public function testTransactionPerDayQuota(): void
    {
        self::$company->quota->set(QuotaType::TransactionsPerDay, 1);

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $this->assertFalse($invoice->save());
        $this->assertEquals('The 1 transaction per day quota has been reached. Please upgrade your account or try again tomorrow to create additional transactions.', (string) $invoice->getErrors());

        self::$company->quota->remove(QuotaType::TransactionsPerDay);
        $this->assertTrue($invoice->save());
    }

    public function testApplyCredits(): void
    {
        self::hasCustomer();
        self::hasInvoice();
        $balance = self::$invoice->refresh()->balance;

        $payment = new Payment();
        $payment->amount = $balance;
        $payment->currency = 'usd';
        $payment->customer = self::$customer->id;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Credit->value,
                'amount' => $balance,
            ],
        ];
        $payment->saveOrFail();
        $this->assertEquals($balance, CreditBalance::lookup(self::$customer, 'usd')->toDecimal());

        $payment = new Payment();
        $payment->amount = $balance;
        $payment->currency = 'usd';
        $payment->date = CarbonImmutable::tomorrow()->unix();
        $payment->customer = self::$customer->id;
        $payment->applied_to = [
            [
                'amount' => $balance,
                'type' => PaymentItemType::AppliedCredit->value,
                'document_type' => 'invoice',
                'invoice' => (int) self::$invoice->id(),
            ],
        ];
        $payment->saveOrFail();
        $this->assertEquals($balance, CreditBalance::lookup(self::$customer, 'usd')->toDecimal());
        $this->assertEquals(0, CreditBalance::lookup(self::$customer, 'usd', CarbonImmutable::tomorrow())->toDecimal());
        $this->assertEquals(0, self::$invoice->refresh()->balance);

        self::hasInvoice();
        self::$invoice->applyCredits();
        $this->assertEquals($balance, self::$invoice->refresh()->balance);
    }
}
