<?php

namespace App\Tests\CashApplication\Models;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\CreditBalance;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\CashApplication\ValueObjects\TransactionTree;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\Metadata\Models\CustomField;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use stdClass;

class TransactionTest extends AppTestCase
{
    private static Customer $customer2;
    private static Transaction $payment1;
    private static Transaction $payment2;
    private static Transaction $payment3;
    private static Transaction $adjustment;
    private static User $ogUser;
    private static ?Model $requester;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        self::hasCustomer();
        self::hasInvoice();
        self::$invoice->currency = 'eur';
        $items = self::$invoice->items();
        $items[] = ['unit_cost' => 50];
        self::$invoice->items = $items;
        self::$invoice->save();

        self::$customer2 = new Customer();
        self::$customer2->name = 'Test 2';
        self::$customer2->save();

        self::$ogUser = self::getService('test.user_context')->get();
        self::$requester = ACLModelRequester::get();

        self::$company->accounts_receivable_settings->auto_apply_credits = false;
        self::$company->accounts_receivable_settings->save();
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

    public function testPdfUrl(): void
    {
        $refund = new Transaction();
        $refund->type = Transaction::TYPE_REFUND;
        $this->assertNull($refund->pdf_url);

        $refund->type = Transaction::TYPE_ADJUSTMENT;
        $this->assertNull($refund->pdf_url);

        $payment = new Transaction();
        $payment->status = Transaction::STATUS_FAILED;
        $this->assertNull($payment->pdf_url);

        $payment = new Transaction();
        $payment->status = Transaction::STATUS_PENDING;
        $this->assertNull($payment->pdf_url);

        $payment = new Transaction();
        $payment->tenant_id = (int) self::$company->id();
        $payment->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/payments/'.self::$company->identifier.'/test/pdf', $payment->pdf_url);
    }

    public function testEventName(): void
    {
        $transaction = new Transaction();
        $this->assertEquals(EventType::TransactionCreated, $transaction->getCreatedEventType());
        $this->assertEquals(EventType::TransactionUpdated, $transaction->getUpdatedEventType());
        $this->assertEquals(EventType::TransactionDeleted, $transaction->getDeletedEventType());

        $transaction->payment = new Payment(['id' => 1234]);
        $this->assertNull($transaction->getCreatedEventType());
        $this->assertNull($transaction->getUpdatedEventType());
        $this->assertNull($transaction->getDeletedEventType());
    }

    public function testEventAssociations(): void
    {
        $transaction = new Transaction();
        $transaction->customer = 100;
        $transaction->invoice = 101;
        $transaction->credit_note_id = 102;
        $transaction->estimate_id = 104;
        $transaction->parent_transaction = 103;

        $expected = [
            ['customer', 100],
            ['invoice', 101],
            ['credit_note', 102],
            ['estimate', 104],
            ['transaction', 103],
        ];

        $this->assertEquals($expected, $transaction->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $transaction = new Transaction();
        $transaction->setCustomer(self::$customer);

        $expected = array_merge($transaction->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
            'payment' => null,
        ]);
        $this->assertEquals($expected, $transaction->getEventObject());
    }

    public function testTree(): void
    {
        $txn = new Transaction();
        $tree = $txn->tree();
        $this->assertInstanceOf(TransactionTree::class, $tree);
        $this->assertEquals($txn, $tree->getRoot());
    }

    public function testCreateInvalidAmount(): void
    {
        $payment = new Transaction();
        $this->assertFalse($payment->create([
            'invoice' => self::$invoice->id(),
            'amount' => 0, ]));
    }

    public function testCreateMismatchedCurrency(): void
    {
        $payment = new Transaction();
        $payment->setInvoice(self::$invoice);
        $payment->amount = 100;
        $payment->currency = 'zar';
        $this->assertFalse($payment->save());
    }

    public function testCreateOverspendBlocked(): void
    {
        $adjustment = new Transaction();

        $adjustment->setCustomer(self::$customer);
        $adjustment->type = Transaction::TYPE_ADJUSTMENT;
        $adjustment->amount = 1000000;
        $this->assertFalse($adjustment->save());

        $this->assertCount(1, $adjustment->getErrors());
        $this->assertEquals('Could not write this change because it caused the customer\'s credit balance to become -$1,000,000.00 on '.date('M j, Y'), $adjustment->getErrors()[0]['message']);
    }

    public function testCreateNegativePaymentBlocked(): void
    {
        $payment = new Transaction();

        $payment->setCustomer(self::$customer);
        $payment->type = Transaction::TYPE_PAYMENT;
        $payment->amount = -10;
        $this->assertFalse($payment->save());

        $this->assertCount(1, $payment->getErrors());
        $this->assertEquals('Creating negative payments is not allowed. Please use a positive amount or create a `refund` transaction instead.', $payment->getErrors()[0]['message']);
    }

    public function testCreateNegativeChargeBlocked(): void
    {
        $charge = new Transaction();
        $charge->setCustomer(self::$customer);
        $charge->type = Transaction::TYPE_CHARGE;
        $charge->amount = -10;
        $this->assertFalse($charge->save());

        $this->assertCount(1, $charge->getErrors());
        $this->assertEquals('Creating negative payments is not allowed. Please use a positive amount or create a `refund` transaction instead.', $charge->getErrors()[0]['message']);
    }

    public function testCreatePositiveRefundBlocked(): void
    {
        $refund = new Transaction();
        $refund->setCustomer(self::$customer);
        $refund->type = Transaction::TYPE_REFUND;
        $refund->amount = -10;
        $this->assertFalse($refund->save());

        $this->assertCount(1, $refund->getErrors());
        $this->assertEquals('Creating negative refunds is not allowed. Please use a positive amount or create a `payment` transaction instead.', $refund->getErrors()[0]['message']);
    }

    public function testCreateCreditNotePaymentBlocked(): void
    {
        $payment = new Transaction();
        $creditNote = new CreditNote(['id' => -2]);
        $creditNote->currency = 'usd';
        $creditNote->balance = 100;

        $payment->setCustomer(self::$customer);
        $payment->setCreditNote($creditNote);
        $payment->setRelation('credit_note', $creditNote);
        $payment->type = Transaction::TYPE_PAYMENT;
        $payment->amount = 100;
        $this->assertFalse($payment->save());

        $this->assertCount(1, $payment->getErrors());
        $this->assertEquals('Only adjustments can be applied to credit notes.', $payment->getErrors()[0]['message']);
    }

    public function testCreateInvalidCustomer(): void
    {
        $transaction = new Transaction();
        $transaction->customer = 12384234;
        $transaction->amount = 100;
        $this->assertFalse($transaction->save());
    }

    public function testCreateInvalidInvoice(): void
    {
        $transaction = new Transaction();
        $transaction->setCustomer(self::$customer);
        $transaction->amount = 100;
        $transaction->invoice = 12384234;
        $this->assertFalse($transaction->save());
    }

    public function testCreateInvalidParentTransaction(): void
    {
        $transaction = new Transaction();
        $transaction->setCustomer(self::$customer);
        $transaction->amount = 100;
        $transaction->parent_transaction = 12384234;
        $this->assertFalse($transaction->save());
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        // create a payment for invoice
        self::$payment1 = new Transaction();
        $this->assertTrue(self::$payment1->create([
            'invoice' => self::$invoice->id(),
            'amount' => 100,
            'date' => mktime(0, 0, 0, 6, 12, 2014),
            'method' => PaymentMethod::CHECK, ]));

        $this->assertEquals(self::$company->id(), self::$payment1->tenant_id);
        $this->assertEquals(100, self::$invoice->refresh()->amount_paid);
        $this->assertEquals(50, self::$invoice->balance);

        // create a second payment for the invoice
        self::$payment2 = new Transaction();
        self::$payment2->setInvoice(self::$invoice);
        self::$payment2->amount = 50;
        self::$payment2->method = PaymentMethod::PAYPAL;
        $this->assertTrue(self::$payment2->save());

        $this->assertEquals(150, self::$invoice->refresh()->amount_paid);
        $this->assertTrue(self::$invoice->closed);
        $this->assertEquals(0, self::$invoice->balance);

        // create a refund
        self::$refund = new Transaction();
        $this->assertTrue(self::$refund->create([
            'invoice' => self::$invoice->id(),
            'type' => Transaction::TYPE_REFUND,
            'parent_transaction' => self::$payment1->id(),
            'amount' => 5,
            'date' => mktime(0, 0, 0, 6, 12, 2014),
            'method' => PaymentMethod::CASH, ]));

        $this->assertEquals(145, self::$invoice->refresh()->amount_paid);
        $this->assertEquals(5, self::$invoice->balance);
        $this->assertEquals(self::$payment1->pdf_url, self::$refund->pdf_url);

        // create a payment for a different customer
        self::$payment3 = new Transaction();
        self::$payment3->setCustomer(self::$customer2);
        self::$payment3->amount = 500;
        $this->assertTrue(self::$payment3->save());
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$payment1, EventType::TransactionCreated);
        $this->assertHasEvent(self::$refund, EventType::TransactionCreated);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        $originalBalance = self::$payment1->invoice()->refresh()->balance; /* @phpstan-ignore-line */
        $originalPaid = self::$payment1->invoice()->amount_paid; /* @phpstan-ignore-line */

        EventSpool::enable();

        // change payment amount from €100 to €105
        self::$payment1->amount = 105;
        self::$payment1->currency = 'USD';
        $this->assertTrue(self::$payment1->save());

        $this->assertEquals($originalPaid + 5, self::$invoice->refresh()->amount_paid);
        $this->assertEquals($originalBalance - 5, self::$invoice->balance);
        $this->assertEquals('eur', self::$payment1->currency);

        EventSpool::disable();

        $originalBalance = self::$invoice->balance;
        $originalPaid = self::$invoice->amount_paid;

        // cannot change transaction type
        self::$payment1->type = Transaction::TYPE_REFUND;
        $this->assertTrue(self::$payment1->save());
        $this->assertEquals(Transaction::TYPE_PAYMENT, self::$payment1->type);
        $this->assertEquals($originalPaid, self::$invoice->refresh()->amount_paid);
        $this->assertEquals($originalBalance, self::$invoice->balance);

        // cannot change refund amount
        self::$refund->amount = 100000;
        $this->assertFalse(self::$refund->save());
        $this->assertEquals($originalPaid, self::$invoice->refresh()->amount_paid);
        $this->assertEquals($originalBalance, self::$invoice->balance);
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$payment1, EventType::TransactionUpdated);
    }

    public function testCannotEditCharge(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 20]];
        $invoice->saveOrFail();

        // should not be able to edit charge transactions (except certain properties)
        $charge = new Transaction();
        $charge->setCustomer(self::$customer);
        $charge->type = Transaction::TYPE_CHARGE;
        $charge->method = PaymentMethod::CREDIT_CARD;
        $charge->setInvoice($invoice);
        $charge->amount = 10;
        $charge->gateway = 'invoiced';
        $charge->gateway_id = '12345';
        $charge->saveOrFail();

        $charge->amount = 9;
        $this->assertFalse($charge->save());

        $charge->clearCache();
        $charge->gateway = 'not invoiced';
        $this->assertFalse($charge->save());

        $charge->clearCache();
        $charge->gateway_id = '678';
        $this->assertFalse($charge->save());

        // should be able to edit some properties
        $charge->clearCache();
        $charge->notes = 'testing...';
        $this->assertTrue($charge->save());
    }

    public function testCannotEditCustomer(): void
    {
        $transaction = new Transaction(['id' => -100, 'customer' => -1, 'tenant_id' => self::$company->id(), 'currency' => 'usd']);
        $transaction->customer = -2;
        $this->assertFalse($transaction->save());
        $this->assertEquals(['Invalid request parameter `customer`. The customer cannot be modified.'], $transaction->getErrors()->all());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$payment1->id(),
            'object' => 'transaction',
            'invoice' => self::$invoice->id(),
            'credit_note' => null,
            'customer' => self::$customer->id(),
            'date' => mktime(0, 0, 0, 6, 12, 2014),
            'type' => Transaction::TYPE_PAYMENT,
            'method' => PaymentMethod::CHECK,
            'gateway' => null,
            'gateway_id' => null,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'currency' => 'eur',
            'amount' => 105,
            'notes' => null,
            'parent_transaction' => null,
            'pdf_url' => 'http://invoiced.localhost:1234/payments/'.self::$company->identifier.'/'.self::$payment1->client_id.'/pdf',
            'metadata' => new stdClass(),
            'created_at' => self::$payment1->created_at,
            'updated_at' => self::$payment1->updated_at,
            'estimate' => null,
            'payment_id' => null,
        ];

        $this->assertEquals($expected, self::$payment1->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testToArrayHook(): void
    {
        $expected = [
            'customerName' => self::$customer->name,
        ];

        $result = [];
        self::$payment1->toArrayHook($result, [], ['customerName' => true], []);

        $this->assertEquals($expected, $result);
    }

    /**
     * @depends testCreate
     */
    public function testChildren(): void
    {
        $expected = [self::$refund->clearCache()->toArray()];
        $expected[0]['invoice'] = self::$invoice->toArray();
        $expected[0]['children'] = [];

        $this->assertEquals($expected, self::$payment1->children);
    }

    /**
     * @depends testCreate
     */
    public function testInvoiceNumber(): void
    {
        $this->assertEquals(self::$invoice->number, self::$payment1->invoice_number);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $payments = Transaction::all();

        $this->assertCount(5, $payments);
        $this->assertEquals(self::$payment1->id(), $payments[0]->id());
        $this->assertEquals(self::$payment2->id(), $payments[1]->id());
        $this->assertEquals(self::$refund->id(), $payments[2]->id());
        $this->assertEquals(self::$payment3->id(), $payments[3]->id());
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

        $this->assertEquals(0, Transaction::count());

        // update the customer territory
        self::$customer->metadata = (object) ['territory' => 'Texas'];
        self::$customer->saveOrFail();

        $payments = Transaction::all();
        $this->assertCount(4, $payments);
        $this->assertEquals(self::$payment1->id(), $payments[0]->id());
        $this->assertEquals(self::$payment2->id(), $payments[1]->id());
        $this->assertEquals(self::$refund->id(), $payments[2]->id());
    }

    /**
     * @depends testCreate
     */
    public function testFindClientId(): void
    {
        $this->assertNull(Transaction::findClientId(''));
        $this->assertNull(Transaction::findClientId('1234'));

        $this->assertEquals(self::$payment1->id(), Transaction::findClientId(self::$payment1->client_id)->id()); /* @phpstan-ignore-line */

        $old = self::$payment1->client_id;
        self::$payment1->refreshClientId();
        $this->assertNotEquals($old, self::$payment1->client_id);

        // set client ID in the past
        self::$payment1->refreshClientId(false, strtotime('-1 year'));
        /** @var Transaction $obj */
        $obj = Transaction::findClientId(self::$payment1->client_id);

        // set the client ID to expire soon
        self::$payment1->refreshClientId(false, strtotime('+29 days'));
        /** @var Transaction $obj */
        $obj = Transaction::findClientId(self::$payment1->client_id);
    }

    /**
     * @depends testCreate
     */
    public function testFindByInvoice(): void
    {
        // logout
        self::getService('test.user_context')->set(new User(['id' => -1]));

        // lookup payment with invoice id
        $payments = Transaction::where('invoice', self::$invoice->id())
            ->all();

        $this->assertCount(3, $payments);
        $this->assertEquals(self::$payment1->id(), $payments[0]->id());
        $this->assertEquals(self::$payment2->id(), $payments[1]->id());
        $this->assertEquals(self::$refund->id(), $payments[2]->id());
    }

    /**
     * @depends testCreate
     */
    public function testPaymentAmount(): void
    {
        $paid = self::$payment1->paymentAmount();
        $this->assertInstanceOf(Money::class, $paid);
        $this->assertEquals('eur', $paid->currency);
        $this->assertEquals(10500, $paid->amount);
    }

    /**
     * @depends testCreate
     */
    public function testAmountRefunded(): void
    {
        $refunded = self::$payment1->amountRefunded();
        $this->assertInstanceOf(Money::class, $refunded);
        $this->assertEquals('eur', $refunded->currency);
        $this->assertEquals(500, $refunded->amount);
    }

    /**
     * @depends testCreate
     */
    public function testGetMethod(): void
    {
        $method = self::$payment1->getMethod();
        $this->assertInstanceOf(PaymentMethod::class, $method);
        $this->assertEquals(self::$company->id(), $method->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testMetadata(): void
    {
        $metadata = self::$payment1->metadata;
        $metadata->test = true;
        self::$payment1->metadata = $metadata;
        $this->assertTrue(self::$payment1->save());
        $this->assertEquals((object) ['test' => true], self::$payment1->metadata);

        self::$payment1->metadata = (object) ['internal.id' => '12345'];
        $this->assertTrue(self::$payment1->save());
        $this->assertEquals((object) ['internal.id' => '12345'], self::$payment1->metadata);

        self::$payment1->metadata = (object) ['array' => [], 'object' => new stdClass()];
        $this->assertTrue(self::$payment1->save());
        $this->assertEquals((object) ['array' => [], 'object' => new stdClass()], self::$payment1->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata(): void
    {
        self::$payment1->metadata = (object) [str_pad('', 41) => 'fail'];
        $this->assertFalse(self::$payment1->save());

        self::$payment1->metadata = (object) ['fail' => str_pad('', 256)];
        $this->assertFalse(self::$payment1->save());

        self::$payment1->metadata = (object) array_fill(0, 11, 'fail');
        $this->assertFalse(self::$payment1->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $invoice = self::$payment1->invoice()->refresh(); /* @phpstan-ignore-line */
        $originalBalance = $invoice->balance;
        $originalPaid = $invoice->amount_paid;

        EventSpool::enable();

        $this->assertTrue(self::$payment1->delete());

        // should also delete any associated refunds
        $this->assertEquals($originalPaid - 105, self::$invoice->refresh()->amount_paid);
        $this->assertEquals($originalBalance + 105, self::$invoice->balance);
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$payment1, EventType::TransactionDeleted);
    }

    public function testCreateAdjustment(): void
    {
        // create a credit balance adjustment
        self::$adjustment = new Transaction();
        $this->assertTrue(self::$adjustment->create([
            'customer' => self::$customer->id(),
            'type' => Transaction::TYPE_ADJUSTMENT,
            'credit_note' => null,
            'amount' => -10,
            'method' => PaymentMethod::OTHER, ]));

        $this->assertNull(self::$adjustment->pdf_url);

        // should create a balance entry
        $balance = CreditBalance::where('transaction_id', self::$adjustment->id())
            ->oneOrNull();
        $this->assertInstanceOf(CreditBalance::class, $balance);
        $this->assertEquals(self::$customer->id(), $balance->customer_id);
        $this->assertEquals(self::$adjustment->date, $balance->timestamp);
        $this->assertEquals(10, $balance->balance);

        // should cache the credit balance
        $this->assertEquals(10, self::$customer->refresh()->credit_balance);
    }

    /**
     * @depends testCreateAdjustment
     */
    public function testEditAdjustment(): void
    {
        EventSpool::enable();

        // change adjustment from $10 to $20
        self::$adjustment->amount = -20;
        $this->assertTrue(self::$adjustment->save());

        // should update the balance entry
        $balance = CreditBalance::where('transaction_id', self::$adjustment->id())
            ->oneOrNull();
        $this->assertInstanceOf(CreditBalance::class, $balance);
        $this->assertEquals(20, $balance->balance);

        // should update the cached credit balance
        $this->assertEquals(20, self::$customer->refresh()->credit_balance);
    }

    /**
     * @depends testCreateAdjustment
     */
    public function testDeleteAdjustment(): void
    {
        $this->assertTrue(self::$adjustment->delete());

        // should delete the balance entry
        $balance = CreditBalance::where('transaction_id', self::$adjustment->id())
            ->oneOrNull();
        $this->assertNull($balance);

        // should update the cached credit balance
        $this->assertEquals(0, self::$customer->refresh()->credit_balance);
    }

    /**
     * @depends testCreate
     */
    public function testAddCredit(): void
    {
        self::$credit = new Transaction();
        self::$credit->type = Transaction::TYPE_ADJUSTMENT;
        self::$credit->setCustomer(self::$customer);
        self::$credit->amount = -50;
        $this->assertTrue(self::$credit->save());

        // should create a balance entry
        $balance = CreditBalance::where('transaction_id', self::$credit->id())
            ->oneOrNull();
        $this->assertInstanceOf(CreditBalance::class, $balance);
        $this->assertEquals(self::$customer->id(), $balance->customer_id);
        $this->assertEquals(self::$credit->date, $balance->timestamp);
        $this->assertEquals(50, $balance->balance);

        // test the balance
        $this->assertEquals(50, CreditBalance::lookup(self::$customer)->toDecimal());

        // should cache the credit balance
        $this->assertEquals(50, self::$customer->refresh()->credit_balance);
    }

    /**
     * @depends testAddCredit
     */
    public function testEditOverspendBlocked(): void
    {
        self::$credit->amount = 2000000;
        $this->assertFalse(self::$credit->save());

        $this->assertCount(1, self::$credit->getErrors());
        $this->assertEquals('Could not write this change because it caused the customer\'s credit balance to become -$2,000,000.00 on '.date('M j, Y'), self::$credit->getErrors()[0]['message']);
    }

    /**
     * @depends testAddCredit
     */
    public function testEditCredit(): void
    {
        self::$credit->amount = -30;
        $this->assertTrue(self::$credit->save());

        // should update the balance entry
        $balance = CreditBalance::where('transaction_id', self::$credit->id())
            ->oneOrNull();
        $this->assertInstanceOf(CreditBalance::class, $balance);
        $this->assertEquals(30, $balance->balance);

        // should update the cached credit balance
        $this->assertEquals(30, self::$customer->refresh()->credit_balance);
    }

    /**
     * @depends testAddCredit
     */
    public function testDeleteCredit(): void
    {
        $this->assertTrue(self::$credit->delete());

        // should delete the balance entry
        $balance = CreditBalance::where('transaction_id', self::$credit->id())
            ->oneOrNull();
        $this->assertNull($balance);

        // should update the cached credit balance
        $this->assertEquals(0, self::$customer->refresh()->credit_balance);
    }

    public function testPendingTransactionSucceeded(): void
    {
        // handle transactions going from pending -> succeeded
        $originalBalance = self::$invoice->refresh()->balance;

        $pending = new Transaction();
        $pending->status = Transaction::STATUS_PENDING;
        $pending->type = Transaction::TYPE_CHARGE;
        $pending->setInvoice(self::$invoice);
        $pending->amount = 10;
        $this->assertTrue($pending->save());
        $this->assertEquals($originalBalance, self::$invoice->refresh()->balance);
        $this->assertEquals(InvoiceStatus::Pending->value, self::$invoice->status);

        // change status to succeeded
        $pending->status = Transaction::STATUS_SUCCEEDED;
        $this->assertTrue($pending->save());
        $this->assertEquals($originalBalance - 10, self::$invoice->refresh()->balance);
    }

    public function testSucceededTransactionFailed(): void
    {
        $originalBalance = self::$invoice->refresh()->balance;

        // create a succeeded transaction
        $failed = new Transaction();
        $failed->status = Transaction::STATUS_SUCCEEDED;
        $failed->type = Transaction::TYPE_CHARGE;
        $failed->setInvoice(self::$invoice);
        $failed->amount = 10;
        $this->assertTrue($failed->save());
        $this->assertEquals($originalBalance - 10, self::$invoice->refresh()->balance);

        // change status to failed
        $failed->status = Transaction::STATUS_FAILED;
        $this->assertTrue($failed->save());
        $this->assertEquals($originalBalance, self::$invoice->refresh()->balance);
        $this->assertFalse(self::$invoice->closed);
    }

    public function testDeleteClosedInvoice(): void
    {
        // issue an invoice
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $this->assertTrue($invoice->save());

        // pay it
        $payment = new Transaction();
        $payment->setInvoice($invoice);
        $payment->amount = $invoice->balance;
        $this->assertTrue($payment->save());

        // delete the payment
        $this->assertTrue($payment->delete());

        // should reopen invoice (and update balance)
        $this->assertFalse($invoice->refresh()->closed);
        $this->assertEquals(100, $invoice->balance);
    }

    /**
     * @depends testAddCredit
     */
    public function testDeleteOverspendBlocked(): void
    {
        $credit = new Transaction();
        $credit->type = Transaction::TYPE_ADJUSTMENT;
        $credit->setCustomer(self::$customer);
        $credit->amount = -100;
        $this->assertTrue($credit->save());

        $adjustment = new Transaction();
        $adjustment->type = Transaction::TYPE_ADJUSTMENT;
        $adjustment->setCustomer(self::$customer);
        $adjustment->amount = 100;
        $this->assertTrue($adjustment->save());

        $this->assertFalse($credit->delete());

        $this->assertCount(1, $credit->getErrors());
        $this->assertEquals('Could not write this change because it caused the customer\'s credit balance to become -$100.00 on '.date('M j, Y'), $credit->getErrors()[0]['message']);
    }

    public function testCreateInheritInvoiceMetadata(): void
    {
        self::$company->accounts_receivable_settings->transactions_inherit_invoice_metadata = true;
        self::$company->accounts_receivable_settings->saveOrFail();

        $customField1 = new CustomField();
        $customField1->id = 'test';
        $customField1->object = ObjectType::Transaction->typeName();
        $customField1->name = 'Test';
        $customField1->saveOrFail();
        $customField2 = new CustomField();
        $customField2->id = 'inherit';
        $customField2->object = ObjectType::Transaction->typeName();
        $customField2->name = 'Transaction Only';
        $customField2->saveOrFail();
        $customField3 = new CustomField();
        $customField3->id = 'invoice_only';
        $customField3->object = ObjectType::Invoice->typeName();
        $customField3->name = 'Invoice Only';
        $customField3->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->metadata = (object) [
            'test' => true,
            'inherit' => 'yes?',
            'invoice_only' => true,
        ];
        $this->assertTrue($invoice->save());

        $payment = new Transaction();
        $payment->setInvoice($invoice);
        $payment->amount = 100;
        $this->assertTrue($payment->save());

        $expected = [
            'test' => true,
            'inherit' => 'yes?',
        ];
        $this->assertEquals($expected, (array) $payment->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testCreditNoteCredit(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $this->assertTrue($invoice->save());

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->setInvoice($invoice);
        $creditNote->items = [['unit_cost' => 200]];
        $this->assertTrue($creditNote->save());

        $credit = new Transaction();
        $credit->type = Transaction::TYPE_ADJUSTMENT;
        $credit->setCustomer(self::$customer);
        $credit->setCreditNote($creditNote);
        $credit->amount = -50;
        $this->assertTrue($credit->save());
        $this->assertEquals(PaymentMethod::BALANCE, $credit->method);

        // should reduce the credit note balance
        $this->assertEquals(50, $creditNote->refresh()->amount_credited);
        $this->assertEquals(50, $creditNote->balance);

        // try editing it
        $credit->amount = -100;
        $this->assertTrue($credit->save());

        // should update the credit note balance
        $this->assertEquals(100, $creditNote->refresh()->amount_credited);
        $this->assertEquals(0, $creditNote->balance);
        $this->assertTrue($creditNote->paid);
        $this->assertTrue($creditNote->closed);

        // try deleting it
        $this->assertTrue($credit->delete());

        // should update the credit note balance
        $this->assertEquals(0, $creditNote->refresh()->amount_credited);
        $this->assertEquals(100, $creditNote->balance);
        $this->assertFalse($creditNote->closed);
        $this->assertFalse($creditNote->paid);
    }

    /**
     * @depends testCreate
     */
    public function testCreditNoteApply(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $this->assertTrue($invoice->save());

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [['unit_cost' => 100]];
        $this->assertTrue($creditNote->save());

        $transaction = new Transaction();
        $transaction->type = Transaction::TYPE_ADJUSTMENT;
        $transaction->setCustomer(self::$customer);
        $transaction->setCreditNote($creditNote);
        $transaction->setInvoice($invoice);
        $transaction->amount = -100;
        $this->assertTrue($transaction->save());

        $this->assertEquals($transaction->refresh()->status, Transaction::STATUS_SUCCEEDED);

        // should reduce the credit note balance
        $this->assertEquals(100, $creditNote->refresh()->amount_applied_to_invoice);
        $this->assertEquals(0, $creditNote->balance);

        // should reduce the invoice balance
        $this->assertEquals(100, $invoice->refresh()->amount_credited);
        $this->assertEquals(0, $invoice->balance);
        $this->assertTrue($creditNote->closed);
        $this->assertTrue($creditNote->paid);

        // try editing it
        $transaction->amount = -50;
        $this->assertTrue($transaction->save());

        // should reduce the credit note balance
        $this->assertEquals(50, $creditNote->refresh()->amount_applied_to_invoice);
        $this->assertEquals(50, $creditNote->balance);

        // should reduce the invoice balance
        $this->assertEquals(50, $invoice->refresh()->amount_credited);
        $this->assertEquals(50, $invoice->balance);
        $this->assertFalse($creditNote->closed);
        $this->assertFalse($creditNote->paid);

        // try deleting it
        $this->assertTrue($transaction->delete());

        // should update the credit note balance
        $this->assertEquals(0, $creditNote->refresh()->amount_applied_to_invoice);
        $this->assertEquals(100, $creditNote->balance);
        $this->assertFalse($creditNote->closed);
        $this->assertFalse($creditNote->paid);
    }

    public function testPaymentAmountWithPaymentObject(): void
    {
        $payment = new Payment();
        $payment->currency = 'usd';
        $payment->amount = 50;

        $transaction = new Transaction();
        $transaction->payment = $payment;
        $this->assertEquals(new Money('usd', 5000), $transaction->paymentAmount());
    }

    public function testAmountsTransactionTree(): void
    {
        $root = new Transaction();
        $root->setCustomer(self::$customer);
        $root->amount = 100;
        $this->assertTrue($root->save());

        $child1 = new Transaction();
        $child1->setCustomer(self::$customer);
        $child1->amount = 200;
        $child1->setParentTransaction($root);
        $this->assertTrue($child1->save());

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 300]];
        $this->assertTrue($invoice->save());

        $child2 = new Transaction();
        $child2->type = Transaction::TYPE_CHARGE;
        $child2->setCustomer(self::$customer);
        $child2->setInvoice($invoice);
        $child2->amount = 300;
        $child2->setParentTransaction($root);
        $this->assertTrue($child2->save());

        $child3 = new Transaction();
        $child3->type = Transaction::TYPE_ADJUSTMENT;
        $child3->setCustomer(self::$customer);
        $child3->amount = -400;
        $child3->setParentTransaction($child2);
        $this->assertTrue($child3->save());

        $child4 = new Transaction();
        $child4->type = Transaction::TYPE_REFUND;
        $child4->setCustomer(self::$customer);
        $child4->amount = 500;
        $child4->setParentTransaction($child2);
        $this->assertTrue($child4->save());

        // verify payment and refund amounts

        $paid = $root->paymentAmount();
        $this->assertInstanceOf(Money::class, $paid);
        $this->assertEquals('usd', $paid->currency);
        $this->assertEquals(60000, $paid->amount);

        $refunded = $root->amountRefunded();
        $this->assertInstanceOf(Money::class, $refunded);
        $this->assertEquals('usd', $refunded->currency);
        $this->assertEquals(50000, $refunded->amount);

        // verify breakdown for payment receipts
        // convert invoice objects into IDs
        $breakdown = $root->breakdown();
        $breakdown = $this->convertBreakdownToIds($breakdown);

        $expected = [
            'invoices' => [
                $invoice->id(),
            ],
            'creditNotes' => [],
            'refunded' => new Money('usd', 50000),
            'credited' => new Money('usd', 40000),
        ];

        $this->assertEquals($expected, $breakdown);
    }

    public function testSkipReconcilliation(): void
    {
        $transaction = new Transaction();
        $payment = new Payment();
        $transaction->payment = $payment;

        $this->assertTrue($transaction->isReconcilable());
        $this->assertTrue($payment->isReconcilable());

        $transaction->skipReconciliation();
        $this->assertFalse($transaction->isReconcilable());
        $this->assertTrue($payment->isReconcilable());

        $payment->skipReconciliation();
        $this->assertFalse($transaction->isReconcilable());
        $this->assertFalse($payment->isReconcilable());

        $transaction = new Transaction();
        $this->assertTrue($transaction->isReconcilable());

        $transaction->payment = $payment;
        $this->assertFalse($transaction->isReconcilable());
        $this->assertFalse($payment->isReconcilable());
    }

    public function testOverApplyCreditNote(): void
    {
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->currency = 'usd';
        $creditNote->items = [
            [
                'name' => 'Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 200,
            ],
        ];
        $creditNote->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';
        $invoice->items = [
            [
                'name' => 'Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice->saveOrFail();

        $transaction = new Transaction();
        $transaction->type = Transaction::TYPE_ADJUSTMENT;
        $transaction->setCustomer(self::$customer);
        $transaction->setInvoice($invoice);
        $transaction->setCreditNote($creditNote);
        $transaction->currency = 'usd';
        $transaction->amount = -200;

        $this->assertTrue($transaction->save());
        $this->assertEquals(0, $creditNote->balance);
        $this->assertEquals(-100, $invoice->balance);
    }

    private function convertBreakdownToIds(array $breakdown): array
    {
        foreach ($breakdown['invoices'] as &$invoice) {
            $invoice = $invoice->id();
        }

        return $breakdown;
    }
}
