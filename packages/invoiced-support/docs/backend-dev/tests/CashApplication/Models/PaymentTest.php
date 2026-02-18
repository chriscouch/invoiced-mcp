<?php

namespace App\Tests\CashApplication\Models;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\CreditBalance;
use App\CashApplication\Models\CreditBalanceAdjustment;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Search\Libs\SearchDocumentFactory;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Metadata\Libs\AttributeHelper;
use App\Metadata\Models\CustomField;
use App\Metadata\ValueObjects\AttributeBoolean;
use App\Metadata\ValueObjects\AttributeDecimal;
use App\Metadata\ValueObjects\AttributeMoney;
use App\Metadata\ValueObjects\AttributeString;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Tests\AppTestCase;
use Doctrine\DBAL\Connection;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Exception\ModelException;

class PaymentTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasEstimate();
    }

    public function testPdfUrl(): void
    {
        $payment = new Payment();
        $payment->tenant_id = (int) self::$company->id();
        $payment->client_id = 'test';
        $payment->customer = -1;
        $payment->applied = false;
        $payment->voided = false;
        $this->assertNull($payment->pdf_url);

        $payment->applied = true;
        $payment->voided = true;
        $this->assertNull($payment->pdf_url);

        $payment->customer = null;
        $payment->voided = false;
        $this->assertNull($payment->pdf_url);

        $payment->customer = -1;
        $this->assertNotNull($payment->pdf_url);
    }

    public function testEventAssociations(): void
    {
        $payment = new Payment();
        $this->assertEquals([], $payment->getEventAssociations());

        $payment->customer = 100;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => 101,
                'amount' => 5,
            ],
            [
                'type' => 'estimate',
                'estimate' => 102,
                'amount' => 6,
            ],
            [
                'type' => 'credit_note',
                'credit_note' => 103,
                'invoice' => 101,
                'amount' => 7,
            ],
        ];
        $expected = [
            ['customer', 100],
            ['invoice', 101],
            ['estimate', 102],
            ['credit_note', 103],
        ];

        $this->assertEquals($expected, $payment->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $payment = new Payment();

        $expected = array_merge($payment->toArray(), [
            'customer' => null,
            'bank_feed_transaction' => null,
            'applied_to' => [],
        ]);
        $this->assertEquals($expected, $payment->getEventObject());

        $payment->setCustomer(self::$customer);
        $expected = array_merge($payment->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
            'bank_feed_transaction' => null,
            'applied_to' => [],
        ]);
        $this->assertEquals($expected, $payment->getEventObject());
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$payment = new Payment();
        self::$payment->date = (int) mktime(0, 0, 0, 6, 12, 2014);
        self::$payment->amount = 200;
        self::$payment->currency = 'usd';
        $this->assertTrue(self::$payment->save());

        $this->assertEquals(self::$company->id(), self::$payment->tenant_id);
        $this->assertEquals(200, self::$payment->balance);
        $this->assertFalse(self::$payment->applied);
    }

    public function testCreateInvalidAmount(): void
    {
        $payment = new Payment();
        $payment->amount = -1;
        $payment->currency = 'usd';

        $this->assertFalse($payment->save());
        $this->assertEquals('The amount cannot be less than 0.', $payment->getErrors()[0]['error']);
        $this->assertFalse($payment->persisted());

        self::$payment->balance = 201;

        $this->assertFalse(self::$payment->save());
        $this->assertEquals('The balance (201 USD) cannot be greater than the amount (200 USD)', self::$payment->getErrors()[0]['error']);
    }

    public function testCreateInvalidCustomer(): void
    {
        $payment = new Payment();
        $payment->customer = -1234;
        $payment->amount = 1;
        $payment->currency = 'usd';

        $this->assertFalse($payment->save());
        $this->assertEquals('No such customer: -1234', $payment->getErrors()[0]['error']);
        $this->assertFalse($payment->persisted());
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$payment, EventType::PaymentCreated);
    }

    /**
     * @depends testCreate
     */
    public function testFindClientId(): void
    {
        $this->assertNull(Payment::findClientId(''));
        $this->assertNull(Payment::findClientId('1234'));

        $this->assertEquals(self::$payment->id(), Payment::findClientId(self::$payment->client_id)->id()); /* @phpstan-ignore-line */

        $old = self::$payment->client_id;
        self::$payment->refreshClientId();
        $this->assertNotEquals($old, self::$payment->client_id);

        // set client ID in the past
        self::$payment->refreshClientId(false, strtotime('-1 year'));
        /** @var Payment $obj */
        $obj = Payment::findClientId(self::$payment->client_id);

        // set the client ID to expire soon
        self::$payment->refreshClientId(false, strtotime('+29 days'));
        /** @var Payment $obj */
        $obj = Payment::findClientId(self::$payment->client_id);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$payment->amount = 110;
        $this->assertTrue(self::$payment->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$payment, EventType::PaymentUpdated);
    }

    /**
     * @depends testCreate
     */
    public function testCalculateBalance(): void
    {
        $this->assertEquals(110, self::$payment->calculateBalance());

        $transaction = new Transaction();
        $transaction->currency = 'usd';
        $transaction->setCustomer(self::$customer);
        $transaction->amount = 110;
        $transaction->payment = self::$payment;
        $this->assertTrue($transaction->save());

        $this->assertEquals(0, self::$payment->calculateBalance());
    }

    /**
     * @depends testCreate
     */
    public function testMarkApplied(): void
    {
        self::$payment->balance = 0;
        self::$payment->saveOrFail();

        $this->assertTrue(self::$payment->applied);
    }

    public function testMatchedStatus(): void
    {
        $payment = new Payment();
        $payment->amount = 100;
        $payment->currency = 'usd';
        $this->assertTrue($payment->save());
        $this->assertNull($payment->matched);

        $payment->matched = false;
        $this->assertTrue($payment->save());
        $this->assertNotNull($payment->matched);
        $this->assertFalse($payment->matched);

        $payment->matched = true;
        $this->assertTrue($payment->save());
        $this->assertTrue($payment->matched);
    }

    public function testAppliedTo(): void
    {
        $payment = new Payment();
        $payment->amount = 100;
        $payment->currency = 'usd';
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'amount' => 20,
                'invoice' => self::$invoice->id(),
            ],
            [
                'type' => PaymentItemType::Estimate->value,
                'amount' => 30,
                'estimate' => self::$estimate->id(),
            ],
        ];
        $payment->saveOrFail();

        $appliedTo = $payment->applied_to;
        $this->assertCount(2, $appliedTo);
        $this->assertNotNull($payment->customer());
        /** @var Transaction $transaction1 */
        $transaction1 = Transaction::find($appliedTo[0]['id']);
        /** @var Transaction $transaction2 */
        $transaction2 = Transaction::find($appliedTo[1]['id']);
        $this->assertNotNull($transaction1);
        $this->assertNotNull($transaction2);
        $this->assertEquals(20, $transaction1->amount);
        $this->assertEquals(-30.0, $transaction2->amount);

        $expected = [
            [
                'id' => $transaction1->id(),
                'type' => 'invoice',
                'invoice' => self::$invoice->id(),
                'amount' => 20.0,
            ],
            [
                'id' => $transaction2->id(),
                'type' => 'estimate',
                'estimate' => self::$estimate->id(),
                'amount' => 30.0,
            ],
        ];
        $this->assertEquals($expected, $appliedTo);

        $payment->applied_to = [
            [
                'id' => $appliedTo[0]['id'],
                'type' => PaymentItemType::Invoice->value,
                'amount' => 30,
                'invoice' => self::$invoice->id(),
            ],
            [
                'type' => PaymentItemType::Credit->value,
                'amount' => 40,
            ],
        ];
        $payment->saveOrFail();

        $appliedTo = $payment->applied_to;
        $this->assertCount(2, $appliedTo);
        $this->assertNotNull($payment->customer());
        /** @var Transaction $transaction1 */
        $transaction1 = Transaction::find($appliedTo[0]['id']);
        /** @var Transaction $transaction2 */
        $transaction2 = Transaction::find($appliedTo[1]['id']);
        $this->assertNotNull($transaction1);
        $this->assertNotNull($transaction2);
        $this->assertEquals(30, $transaction1->amount);
        $this->assertEquals(-40, $transaction2->amount);

        $expected = [
            [
                'id' => $transaction1->id(),
                'type' => 'invoice',
                'invoice' => self::$invoice->id(),
                'amount' => 30,
            ],
            [
                'id' => $transaction2->id(),
                'type' => 'credit',
                'amount' => 40,
            ],
        ];
        $this->assertEquals($expected, $appliedTo);
    }

    public function testCannotApplyDuplicates(): void
    {
        $payment = new Payment();
        $payment->amount = 100;
        $payment->currency = 'usd';
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'amount' => 1,
                'invoice' => self::$invoice->id(),
            ],
            [
                'type' => PaymentItemType::Invoice->value,
                'amount' => 2,
                'invoice' => self::$invoice->id(),
            ],
        ];
        $this->assertFalse($payment->save());

        // Assert that the error message that occurs
        // when duplicate model instances are provided
        // does not include the string representation
        // of the model instance, and instead includes
        // the model's id.
        $payment = new Payment();
        $payment->amount = 100;
        $payment->currency = 'usd';
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'amount' => 1,
                'invoice' => self::$invoice,
            ],
            [
                'type' => PaymentItemType::Invoice->value,
                'amount' => 2,
                'invoice' => self::$invoice,
            ],
        ];
        $this->assertFalse($payment->save());

        // Asserting against the error message's length to
        // ensure a string representation of a model is
        // not included. 140 is just above that of the
        // expected error message length, but with a little
        // padding for variable id length.
        $error = (string) $payment->getErrors();
        $this->assertLessThan(140, strlen($error));
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$payment->id(),
            'object' => 'payment',
            'customer' => null,
            'date' => (int) mktime(0, 0, 0, 6, 12, 2014),
            'method' => PaymentMethod::OTHER,
            'currency' => 'usd',
            'amount' => 110.0,
            'notes' => null,
            'voided' => false,
            'source' => 'keyed',
            'matched' => null,
            'reference' => null,
            'ach_sender_id' => null,
            'bank_feed_transaction_id' => null,
            'balance' => 0.0,
            'pdf_url' => null,
            'charge' => null,
            'created_at' => self::$payment->created_at,
            'updated_at' => self::$payment->updated_at,
            'metadata' => new \stdClass(),
            'surcharge_percentage' => 0.0
        ];

        $this->assertEquals($expected, self::$payment->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testToSearchDocument(): void
    {
        $expected = [
            'date' => (int) mktime(0, 0, 0, 6, 12, 2014),
            'method' => PaymentMethod::OTHER,
            'currency' => 'usd',
            'amount' => 110.0,
            'voided' => false,
            'source' => 'keyed',
            'reference' => null,
            'balance' => 0.0,
            'charge' => null,
            '_customer' => null,
            'customer' => null,
        ];

        $this->assertEquals($expected, (new SearchDocumentFactory())->make(self::$payment));
    }

    /**
     * @depends testCreate
     *
     * @doesNotPerformAssertions
     */
    public function testEmail(): void
    {
        self::$payment->setCustomer(self::$customer);
        self::$payment->saveOrFail();
        $emailTemplate = (new DocumentEmailTemplateFactory())->get(self::$payment);
        self::getService('test.email_spool')->spoolDocument(self::$payment, $emailTemplate)->flush();
    }

    public function testVoid(): void
    {
        EventSpool::enable();
        self::$payment->void();
        $this->assertTrue(self::$payment->voided);
    }

    /**
     * @depends testVoid
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$payment, EventType::PaymentDeleted);
    }

    /**
     * @depends testVoid
     */
    public function testVoidNotAllowed(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('This payment has already been voided.');

        self::$payment->void();
    }

    /**
     * @depends testVoid
     */
    public function testEditNotAllowed(): void
    {
        self::$payment->amount = 110;
        $this->assertFalse(self::$payment->save());
        $this->assertEquals('The payment is voided and cannot be edited.', self::$payment->getErrors());
    }

    public function testDeleteCreditInvalid(): void
    {
        $customer = new Customer();
        $customer->name = 'INVD-1678';
        $customer->saveOrFail();

        // create 2 invoices
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 40]];
        $invoice->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer($customer);
        $invoice2->items = [['unit_cost' => 45]];
        $invoice2->saveOrFail();

        // create and apply the payment
        $payment = new Payment();
        $payment->setCustomer($customer);
        $payment->currency = 'usd';
        $payment->amount = 45;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice->id(),
                'amount' => 40,
            ],
            [
                'type' => 'credit',
                'amount' => 5,
            ],
        ];
        $payment->saveOrFail();

        // apply the credit
        $creditApplication = new Transaction();
        $creditApplication->type = Transaction::TYPE_CHARGE;
        $creditApplication->method = PaymentMethod::BALANCE;
        $creditApplication->setInvoice($invoice2);
        $creditApplication->amount = 5;
        $creditApplication->saveOrFail();

        // remove the credit
        $payment->applied_to = [
            [
                'type' => 'payment',
                'invoice' => $invoice->id(),
                'amount' => 40,
            ],
        ];
        $this->assertFalse($payment->save());
    }

    public function testPaymentWithConvenienceFee(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        // create and apply the payment
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->currency = 'usd';
        $payment->amount = 45;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice,
                'amount' => 40,
            ],
            [
                'type' => 'convenience_fee',
                'amount' => 5,
            ],
        ];
        $payment->saveOrFail();

        $expected = [
            [
                'type' => 'invoice',
                'invoice' => $invoice->id(),
                'amount' => 40,
            ],
            [
                'type' => 'convenience_fee',
                'amount' => 5,
            ],
        ];
        $appliedTo = $payment->applied_to;
        foreach ($appliedTo as &$row) {
            if (isset($row['id'])) {
                unset($row['id']);
            }
        }
        $this->assertEquals($expected, $appliedTo);
    }

    public function testNoDuplicateEvents(): void
    {
        EventSpool::enable();

        $customer = new Customer();
        $customer->name = 'INVD-1896';
        $customer->saveOrFail();

        // create and apply the payment
        $payment = new Payment();
        $payment->setCustomer($customer);
        $payment->currency = 'usd';
        $payment->amount = 45;
        $payment->applied_to = [
            [
                'type' => 'credit',
                'amount' => 45,
            ],
        ];
        $payment->saveOrFail();

        $this->assertHasEvent($payment, EventType::PaymentCreated);

        // INVD-1896: Should not create a payment.updated event
        $this->assertHasEvent($payment, EventType::PaymentUpdated, 0);

        // editing applied to should create one updated event
        $payment->applied_to = [
            [
                'type' => 'credit',
                'amount' => 40,
            ],
        ];
        $payment->saveOrFail();

        $this->assertHasEvent($payment, EventType::PaymentUpdated);
    }

    public function testEditAppliedToCredit(): void
    {
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 100;
        $payment->applied_to = [['type' => 'credit', 'amount' => 100]];
        $payment->saveOrFail();

        // editing the payment with the same application should work.
        // this tests a scenario in which the positive credit amount
        // does not translate into a negative transaction amount.
        $transactions = $payment->getTransactions();
        $payment->applied_to = [
            [
                'id' => $transactions[0]->id(),
                'type' => 'credit',
                'amount' => 100,
            ],
        ];

        $this->assertTrue($payment->save());
    }

    public function testVoidInvd1908(): void
    {
        $customer = new Customer();
        $customer->name = 'INVD-1908';
        $customer->saveOrFail();

        $invoice1 = new Invoice();
        $invoice1->setCustomer($customer);
        $invoice1->items = [['unit_cost' => 100]];
        $invoice1->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer($customer);
        $invoice2->items = [['unit_cost' => 100]];
        $invoice2->saveOrFail();

        $payment1 = new Payment();
        $payment1->setCustomer($customer);
        $payment1->amount = 10;
        $payment1->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice2,
                'amount' => 10,
            ],
        ];
        $payment1->saveOrFail();

        $payment2 = new Payment();
        $payment2->setCustomer($customer);
        $payment2->amount = 40;
        $payment2->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice1,
                'amount' => 20,
            ],
            [
                'type' => 'invoice',
                'invoice' => $invoice2,
                'amount' => 20,
            ],
        ];
        $payment2->saveOrFail();

        $payment2->void();

        $this->assertEquals(100, $invoice1->refresh()->balance);
        $this->assertEquals(90, $invoice2->refresh()->balance);
    }

    public function testApplyCreditNote(): void
    {
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->currency = 'usd';
        $payment->amount = 100;
        $payment->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $creditNote->saveOrFail();

        $splits = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => self::$invoice,
                'credit_note' => $creditNote,
                'amount' => 10,
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'credit_note' => $creditNote,
                'amount' => 10,
            ],
        ];

        $payment->applied_to = $splits;
        $payment->saveOrFail();

        $this->assertEquals(100, $payment->balance);
        $this->assertEquals(80, $creditNote->balance);

        $transactions = Transaction::queryWithTenant(self::$company)
            ->where('payment_id', $payment)
            ->all();

        $expected = [
            'id' => $transactions[0]->id(),
            'object' => 'transaction',
            'invoice' => self::$invoice->id(),
            'credit_note' => $creditNote->id(),
            'customer' => self::$customer->id(),
            'date' => $payment->date,
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::OTHER,
            'gateway' => null,
            'gateway_id' => null,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'currency' => 'usd',
            'amount' => -10.0,
            'notes' => null,
            'parent_transaction' => null,
            'pdf_url' => $payment->pdf_url,
            'metadata' => (object) [],
            'created_at' => $transactions[0]->created_at,
            'updated_at' => $transactions[0]->updated_at,
            'estimate' => null,
            'payment_id' => $payment->id(),
        ];

        $this->assertEquals($expected, $transactions[0]->toArray());

        $expected = [
            'id' => $transactions[1]->id(),
            'object' => 'transaction',
            'invoice' => null,
            'credit_note' => $creditNote->id(),
            'customer' => self::$customer->id(),
            'date' => $payment->date,
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::BALANCE,
            'gateway' => null,
            'gateway_id' => null,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'currency' => 'usd',
            'amount' => -10.0,
            'notes' => null,
            'parent_transaction' => $transactions[0]->id,
            'pdf_url' => $payment->pdf_url,
            'metadata' => (object) [],
            'created_at' => $transactions[1]->created_at,
            'updated_at' => $transactions[1]->updated_at,
            'estimate' => null,
            'payment_id' => $payment->id(),
        ];

        $this->assertEquals($expected, $transactions[1]->toArray());
    }

    public function testApplyZero(): void
    {
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->currency = 'usd';
        $payment->amount = 0;
        $payment->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $creditNote->saveOrFail();

        $splits = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'credit_note' => $creditNote,
                'amount' => 100,
            ],
        ];

        $payment->applied_to = $splits;
        $payment->saveOrFail();

        $this->assertEquals(0, $payment->balance);
        $this->assertEquals(0, $creditNote->balance);

        $transactions = Transaction::queryWithTenant(self::$company)
            ->where('payment_id', $payment)
            ->all();

        $expected = [
            'id' => $transactions[0]->id(),
            'object' => 'transaction',
            'invoice' => null,
            'credit_note' => $creditNote->id(),
            'customer' => self::$customer->id(),
            'date' => $payment->date,
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::BALANCE,
            'gateway' => null,
            'gateway_id' => null,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'currency' => 'usd',
            'amount' => -100.0,
            'notes' => null,
            'parent_transaction' => null,
            'pdf_url' => $payment->pdf_url,
            'metadata' => (object) [],
            'created_at' => $transactions[0]->created_at,
            'updated_at' => $transactions[0]->updated_at,
            'estimate' => null,
            'payment_id' => $payment->id(),
        ];

        $this->assertEquals($expected, $transactions[0]->toArray());
    }

    public function testGetAppliedTo(): void
    {
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->currency = 'usd';
        $payment->amount = 100;
        $payment->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $creditNote->saveOrFail();

        $splits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
                'amount' => 10.0,
            ],
            [
                'type' => PaymentItemType::Credit->value,
                'amount' => 5.0,
            ],
            [
                'type' => PaymentItemType::Estimate->value,
                'estimate' => self::$estimate,
                'amount' => 15.0,
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => self::$invoice,
                'credit_note' => $creditNote,
                'amount' => 10.0,
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'estimate',
                'estimate' => self::$estimate,
                'credit_note' => $creditNote,
                'amount' => 10.0,
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'credit_note' => $creditNote,
                'amount' => 10.0,
            ],
        ];

        $payment->applied_to = $splits;
        $payment->saveOrFail();

        $this->assertEquals(70, $payment->balance);

        $transactions = $payment->getTransactions();
        $this->assertEquals(6, count($transactions));
        $this->assertEquals(6, count($payment->applied_to));

        $expected = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
                'amount' => 10.0,
                'id' => $transactions[0]->id(),
            ],
            [
                'type' => PaymentItemType::Credit->value,
                'amount' => 5.0,
                'id' => $transactions[1]->id(),
            ],
            [
                'type' => PaymentItemType::Estimate->value,
                'estimate' => self::$estimate->id(),
                'amount' => 15.0,
                'id' => $transactions[2]->id(),
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => self::$invoice->id(),
                'credit_note' => $creditNote->id(),
                'amount' => 10.0,
                'id' => $transactions[3]->id(),
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'estimate',
                'estimate' => self::$estimate->id(),
                'credit_note' => $creditNote->id(),
                'amount' => 10.0,
                'id' => $transactions[4]->id(),
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'credit_note' => $creditNote->id(),
                'amount' => 10.0,
                'id' => $transactions[5]->id(),
            ],
        ];

        $this->assertEquals($expected, $payment->applied_to);
    }

    public function testReduceFullyAppliedPayment(): void
    {
        $customer = new Customer();
        $customer->name = 'Fully Applied';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer($customer);
        $payment->currency = 'usd';
        $payment->amount = 100;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 100,
            ],
        ];
        $payment->saveOrFail();
        $this->assertEquals(0, $payment->balance);

        // reducing the amount without reducing application should fail
        $payment->amount = 50;
        $this->assertFalse($payment->save());

        // reducing the amount and supplying a higher application should fail
        $payment->amount = 50;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 100,
            ],
        ];
        $this->assertFalse($payment->save());

        // reducing the amount AND reducing application should succeed
        $payment->amount = 50;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 50,
            ],
        ];
        $payment->saveOrFail();
        $this->assertTrue($payment->save());
        $this->assertEquals(0, $payment->balance);
    }

    public function testAppliedCredit(): void
    {
        $customer = new Customer();
        $customer->name = 'Applied Credit';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 300]];
        $invoice->saveOrFail();

        $adjustment = new CreditBalanceAdjustment();
        $adjustment->setCustomer($customer);
        $adjustment->amount = 200;
        $adjustment->saveOrFail();

        $payment = new Payment();
        $payment->amount = 100;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 100,
            ],
            [
                'type' => 'applied_credit',
                'document_type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 200,
            ],
        ];
        $payment->saveOrFail();
        $this->assertTrue($payment->save());
        $this->assertEquals(0, $payment->balance);
        $this->assertTrue($invoice->paid);
        $this->assertEquals(0, CreditBalance::lookup($customer)->toDecimal());

        $transactions = $payment->getTransactions();
        $this->assertCount(2, $transactions);
        $expected = [
            [
                'id' => $transactions[0]->id(),
                'type' => 'invoice',
                'invoice' => $invoice->id(),
                'amount' => 100.0,
            ],
            [
                'id' => $transactions[1]->id(),
                'type' => 'applied_credit',
                'document_type' => 'invoice',
                'invoice' => $invoice->id(),
                'amount' => 200.0,
            ],
        ];
        $this->assertEquals($expected, $payment->applied_to);
    }

    public function testParentPayment(): void
    {
        $customer = new Customer();
        $customer->name = 'Parent';
        $customer->saveOrFail();
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $customer2 = new Customer();
        $customer2->name = 'Level 1';
        $customer2->setParentCustomer($customer);
        $customer2->saveOrFail();
        $invoice2 = new Invoice();
        $invoice2->setCustomer($customer2);
        $invoice2->items = [['unit_cost' => 100]];
        $invoice2->saveOrFail();

        $customer3 = new Customer();
        $customer3->name = 'Level 2';
        $customer3->setParentCustomer($customer2);
        $customer3->saveOrFail();
        $invoice3 = new Invoice();
        $invoice3->setCustomer($customer3);
        $invoice3->items = [['unit_cost' => 100]];
        $invoice3->saveOrFail();

        $payment = new Payment();
        $payment->amount = 300;
        $payment->setCustomer($customer);
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice,
                'amount' => 100,
            ],
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice2,
                'amount' => 100,
            ],
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice3,
                'amount' => 100,
            ],
        ];
        $this->assertTrue($payment->save());

        $this->assertTrue($invoice->paid);
        $this->assertTrue($invoice2->paid);
        $this->assertTrue($invoice3->paid);
    }

    public function testToArrayCharge(): void
    {
        $charge = new Charge([
            'id' => -456,
            'status' => Charge::SUCCEEDED,
            'amount' => 100.0,
            'currency' => 'usd',
            'customer_id' => self::$customer->id(),
            'gateway' => 'invoiced',
            'gateway_id' => '1234',
            'payment_id' => -123,
        ]);

        $payment = new Payment([
            'id' => -123,
            'customer' => self::$customer->id(),
            'date' => (int) mktime(0, 0, 0, 6, 12, 2014),
            'method' => PaymentMethod::CREDIT_CARD,
            'currency' => 'usd',
            'amount' => 100.0,
            'balance' => 0.0,
            'reference' => '1234',
            'charge' => $charge,
            'source' => 'customer_portal',
            'voided' => false,
        ]);

        $expected = [
            'id' => -123,
            'object' => 'payment',
            'customer' => self::$customer->id(),
            'date' => (int) mktime(0, 0, 0, 6, 12, 2014),
            'method' => PaymentMethod::CREDIT_CARD,
            'currency' => 'usd',
            'amount' => 100.0,
            'notes' => null,
            'voided' => false,
            'source' => 'customer_portal',
            'matched' => null,
            'reference' => '1234',
            'ach_sender_id' => null,
            'bank_feed_transaction_id' => null,
            'balance' => 0.0,
            'pdf_url' => null,
            'charge' => [
                'amount' => 100.0,
                'amount_refunded' => 0.0,
                'created_at' => null,
                'currency' => 'usd',
                'customer_id' => self::$customer->id(),
                'description' => null,
                'disputed' => false,
                'failure_message' => null,
                'gateway' => 'invoiced',
                'gateway_id' => '1234',
                'id' => -456,
                'merchant_account_id' => null,
                'merchant_account_transaction_id' => null,
                'object' => 'charge',
                'payment_flow_id' => null,
                'payment_id' => -123,
                'payment_source' => null,
                'receipt_email' => null,
                'refunded' => false,
                'refunds' => [],
                'status' => 'succeeded',
                'updated_at' => null,
            ],
            'created_at' => null,
            'updated_at' => null,
            'metadata' => new \stdClass(),
            'surcharge_percentage' => 0.0
        ];

        $this->assertEquals($expected, $payment->toArray());
    }

    public function testQueryRestrictions(): void
    {
        $requester = ACLModelRequester::get();
        self::hasCustomer();
        self::hasPayment(self::$customer);

        self::hasCustomer();
        self::hasPayment(self::$customer);

        $member = new Member();
        $member->role = Role::ADMINISTRATOR;
        $member->setUser(self::getService('test.user_context')->get());
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];

        ACLModelRequester::set($member);

        $this->assertEquals(0, Payment::count());

        // update the customer territory
        self::$customer->metadata = (object) ['territory' => 'Texas'];
        self::$customer->saveOrFail();

        $this->assertEquals(1, Payment::count());

        self::hasCustomer();
        self::hasPayment(self::$customer);
        $oldCustomer = self::$customer;
        self::hasCustomer();
        self::hasPayment(self::$customer);

        $member->restriction_mode = Member::OWNER_RESTRICTION;

        ACLModelRequester::set($member);

        $this->assertEquals(0, Payment::count());

        // update the customer territory
        self::$customer->owner = $member->user();
        self::$customer->saveOrFail();
        $this->assertEquals(1, Payment::count());

        // update the customer territory
        $oldCustomer->owner = $member->user();
        self::$customer->relation('owner');
        $oldCustomer->saveOrFail();

        $this->assertEquals(2, Payment::count());

        ACLModelRequester::set($requester);
    }

    public function testReconcilable(): void
    {
        $payment = new Payment();
        $this->assertTrue($payment->isReconcilable());
        $payment->skipReconciliation();
        $this->assertFalse($payment->isReconcilable());
        $payment->source = AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM;
        $this->assertFalse($payment->isReconcilable());

        $payment = new Payment();
        $this->assertTrue($payment->isReconcilable());
        $payment->source = AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM;
        $this->assertFalse($payment->isReconcilable());
        $payment->source = AccountingPaymentMapping::SOURCE_INVOICED;
        $this->assertTrue($payment->isReconcilable());
        $payment->skipReconciliation();
        $this->assertFalse($payment->isReconcilable());
    }

    public function testInvd2639(): void
    {
        self::$company->accounts_receivable_settings->auto_apply_credits = false;
        self::$company->accounts_receivable_settings->saveOrFail();

        $customer = new Customer();
        $customer->name = 'INVD-2639';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 500]];
        $invoice->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer($customer);
        $payment->amount = 550;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 500,
            ],
            [
                'type' => 'credit',
                'amount' => 50,
            ],
        ];
        $payment->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer($customer);
        $invoice2->items = [['unit_cost' => 50]];
        $invoice2->saveOrFail();

        $payment2 = new Payment();
        $payment2->setCustomer($customer);
        $payment2->amount = 0;
        $payment2->applied_to = [
            [
                'type' => 'applied_credit',
                'document_type' => 'invoice',
                'invoice' => $invoice2,
                'amount' => 50,
            ],
        ];
        $payment2->saveOrFail();

        try {
            $payment->void();
        } catch (ModelException $e) {
            // this is expected to fail
        }
        $payment2->void();
        $this->assertEquals(50, CreditBalance::lookup($customer)->toDecimal());

        // now we can void given correct order of operations
        $payment->void();
        $this->assertEquals(0, CreditBalance::lookup($customer)->toDecimal());
    }

    /**
     * covers \AttributeHelper::clean
     * covers \AttributeHelper::getAttributes
     * covers \AttributeHelper::setAttributes
     * covers \AttributeHelper::get.
     */
    public function testPaymentMetadata(): void
    {
        self::hasPayment();
        /** @var Connection $database */
        $database = self::getService('test.database');
        $tenantId = self::$company->id;
        $paymentId = self::$payment->id;
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->id = 2;
        $metadata = [
            'string' => 'string',
            'boolean' => true,
            'integer' => 2,
            'decimal' => 1.01,
            'money' => Money::fromDecimal('usd', 1.01),
            'timestamp' => 123456789,
            'datetime' => '2022-01-02 00:00:00',
            'jsonString' => '{xxx: xxx}',
            'object' => $invoice,
            'jsonMoney' => ['currency' => 'usd', 'amount' => 0.01],
        ];
        self::$payment->metadata = (object) $metadata;
        self::$payment->saveOrFail();

        $res = $database->executeQuery("SELECT name, type FROM PaymentAttributes where tenant_id = {$tenantId}")->fetchAll();
        $attributes = [];
        foreach ($res as $item) {
            $attributes[$item['name']] = (int) $item['type'];
        }

        $this->assertEquals([
            'string' => AttributeHelper::TYPE_STRING,
            'boolean' => AttributeHelper::TYPE_STRING,
            'integer' => AttributeHelper::TYPE_STRING,
            'decimal' => AttributeHelper::TYPE_STRING,
            'money' => AttributeHelper::TYPE_STRING,
            'timestamp' => AttributeHelper::TYPE_STRING,
            'datetime' => AttributeHelper::TYPE_STRING,
            'jsonString' => AttributeHelper::TYPE_STRING,
            'jsonMoney' => AttributeHelper::TYPE_STRING,
            'object' => AttributeHelper::TYPE_STRING,
        ], $attributes);

        $values = $database->executeQuery("SELECT value FROM PaymentStringValues where object_id = {$paymentId}")->fetchFirstColumn();
        $this->assertEquals([
            'string',
            '1',
            '2',
            '1.01',
            // the value is displayed like this - because this is
            // the Payment object
            '1.01 USD',
            '123456789',
            '2022-01-02 00:00:00',
            '{xxx: xxx}',
            '',
            // the value is displayed like this - because this is string
            // representation of JSON object
            // value would be represented properly, if custom field
            // with money type would be created
            '{"currency":"usd","amount":0.01}',
        ], $values);

        // test clean
        /** @var AttributeHelper $helper */
        $helper = self::getService('test.attribute_helper');
        $helper->clean();

        foreach (['PaymentMoneyValues', 'PaymentStringValues', 'PaymentIntegerValues', 'PaymentDecimalValues'] as $table) {
            $this->assertEquals(0, $database->executeQuery("SELECT * FROM $table where object_id = {$paymentId}")->rowCount());
        }
    }

    public function testPaymentCustomFieldsMetadata(): void
    {
        CustomField::query()->delete();
        /** @var Connection $database */
        $database = self::getService('test.database');
        $database->executeQuery('DELETE FROM PaymentAttributes');
        self::hasPayment();
        $tenantId = self::$company->id;
        $paymentId = self::$payment->id;

        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->id = 2;
        $metadata = [
            'string' => 'string',
            'boolean' => true,
            'integer' => 2,
            'decimal' => 1.01,
            'money' => Money::fromDecimal('usd', 1.01),
            'timestamp' => 123456789,
            'datetime' => '2022-01-02 00:00:00',
            'jsonMoney' => ['currency' => 'usd', 'amount' => 0.01],
        ];

        $customFields = [
            'string' => CustomField::FIELD_TYPE_STRING,
            'boolean' => CustomField::FIELD_TYPE_BOOLEAN,
            'integer' => CustomField::FIELD_TYPE_DOUBLE,
            'decimal' => CustomField::FIELD_TYPE_DOUBLE,
            'money' => CustomField::FIELD_TYPE_MONEY,
            'timestamp' => CustomField::FIELD_TYPE_DATE,
            'datetime' => CustomField::FIELD_TYPE_DATE,
            'jsonMoney' => CustomField::FIELD_TYPE_MONEY,
            'jsonMoneyArray' => CustomField::FIELD_TYPE_MONEY,
        ];

        foreach ($customFields as $name => $type) {
            $field = new CustomField();
            $field->type = $type;
            $field->name = $name;
            $field->id = $name;
            $field->object = ObjectType::Payment->typeName();
            $field->saveOrFail();
        }

        self::$payment->metadata = (object) $metadata;
        self::$payment->saveOrFail();

        $res = $database->executeQuery("SELECT name, type FROM PaymentAttributes where tenant_id = {$tenantId}")->fetchAll();
        $attributes = [];
        foreach ($res as $item) {
            $attributes[$item['name']] = (int) $item['type'];
        }

        $this->assertEquals([
            'string' => AttributeHelper::TYPE_STRING,
            'boolean' => AttributeHelper::TYPE_BOOLEAN,
            'integer' => AttributeHelper::TYPE_DECIMAL,
            'decimal' => AttributeHelper::TYPE_DECIMAL,
            'money' => AttributeHelper::TYPE_MONEY,
            'timestamp' => AttributeHelper::TYPE_STRING,
            'datetime' => AttributeHelper::TYPE_STRING,
            'jsonMoney' => AttributeHelper::TYPE_MONEY,
        ], $attributes);

        $values = $database->executeQuery("SELECT ROUND(value, 2) as value, currency FROM PaymentMoneyValues where object_id = {$paymentId}")->fetchAll();
        $this->assertEquals([
            ['value' => 1.01, 'currency' => 'usd'],
            ['value' => 0.01, 'currency' => 'usd'],
        ], $values);

        $values = $database->executeQuery("SELECT value FROM PaymentStringValues where object_id = {$paymentId}")->fetchFirstColumn();
        $this->assertEquals([
            'string',
            '123456789',
            '2022-01-02 00:00:00',
        ], $values);

        $values = $database->executeQuery("SELECT value FROM PaymentIntegerValues where object_id = {$paymentId}")->fetchFirstColumn();
        $this->assertEquals([
            1,
        ], $values);

        $values = $database->executeQuery("SELECT value FROM PaymentDecimalValues where object_id = {$paymentId}")->fetchFirstColumn();
        $this->assertEquals([
            2,
            1.01,
        ], $values);

        $this->assertEquals([
            'string' => 'string',
            'boolean' => 1,
            'integer' => 2,
            'decimal' => '1.0100000000',
            'money' => Money::fromDecimal('usd', 1.01),
            'timestamp' => 123456789,
            'datetime' => '2022-01-02 00:00:00',
            'jsonMoney' => Money::fromDecimal('usd', 0.01),
        ], (array) Payment::findOrFail(self::$payment->id)->metadata);

        $metadata = [
            'string' => 'string2',
            'boolean' => false,
            'integer' => 3,
            'decimal' => 4.04,
            'money' => Money::fromDecimal('usd', 5.05),
            'timestamp' => 987654321,
            'datetime' => '2023-01-02 00:00:00',
            'jsonString' => '{foo: bar}',
            'jsonMoney' => ['currency' => 'usd', 'amount' => 0.06],
            'jsonMoneyArray' => ['currency' => 'usd', 'amount' => 0.02, 'test' => 'test'],
        ];
        self::$payment->metadata = (object) $metadata;
        self::$payment->saveOrFail();
        $this->assertEquals([
            'string' => 'string2',
            'boolean' => 0,
            'integer' => 3,
            'decimal' => 4.04,
            'money' => Money::fromDecimal('usd', 5.05),
            'timestamp' => 987654321,
            'datetime' => '2023-01-02 00:00:00',
            'jsonString' => '{foo: bar}',
            'jsonMoney' => Money::fromDecimal('usd', 0.06),
            'jsonMoneyArray' => Money::fromDecimal('usd', 0.02),
        ], (array) Payment::findOrFail(self::$payment->id)->metadata);

        self::$payment->metadata = (object) [
            'string' => 1,
            'jsonString' => 1.01,
            'decimal' => 4,
            'datetime' => Money::fromDecimal('usd', 0.02),
        ];
        self::$payment->saveOrFail();
        // valid metadata of different kind
        // ie saving ints to decimal
        $expectedmetadata = [
            'string' => 1,
            'jsonString' => 1.01,
            'decimal' => 4,
            'datetime' => '0.02 USD',
        ];
        $this->assertEquals($expectedmetadata, (array) Payment::findOrFail(self::$payment->id)->metadata);

        // test defaults
        $metadata = [
            'boolean' => false,
            'integer' => 0,
            'decimal' => 0.0,
        ];
        self::$payment->metadata = (object) $metadata;
        self::$payment->saveOrFail();
        $this->assertEquals([
            'boolean' => 0,
            'integer' => 0,
            'decimal' => 0,
        ], (array) Payment::findOrFail(self::$payment->id)->metadata);

        /** @var AttributeHelper $helper */
        $helper = self::getService('test.attribute_helper');
        $helper->build(self::$payment);

        $attributes = $helper->getAttributes(['string', 'integer', 'jsonMoney', 'decimal', 'boolean']);
        $this->assertInstanceOf(AttributeString::class, $attributes['string']);
        $this->assertInstanceOf(AttributeDecimal::class, $attributes['decimal']);
        $this->assertInstanceOf(AttributeDecimal::class, $attributes['integer']);
        $this->assertInstanceOf(AttributeMoney::class, $attributes['jsonMoney']);
        $this->assertInstanceOf(AttributeBoolean::class, $attributes['boolean']);

        $attributes = $helper->getAllAttributes();
        $this->assertCount(10, $attributes);
        $this->assertInstanceOf(AttributeString::class, $attributes['string']);
        $this->assertInstanceOf(AttributeDecimal::class, $attributes['decimal']);
        $this->assertInstanceOf(AttributeDecimal::class, $attributes['integer']);
        $this->assertInstanceOf(AttributeMoney::class, $attributes['jsonMoney']);
        $this->assertInstanceOf(AttributeBoolean::class, $attributes['boolean']);
        $this->assertInstanceOf(AttributeMoney::class, $attributes['money']);
        $this->assertInstanceOf(AttributeString::class, $attributes['timestamp']);
        $this->assertInstanceOf(AttributeString::class, $attributes['datetime']);
        $this->assertInstanceOf(AttributeString::class, $attributes['jsonString']);
        $this->assertInstanceOf(AttributeMoney::class, $attributes['jsonMoneyArray']);

        // invalid metadata testing
        $metadatas = [
            ['boolean' => 'xxx'],
            ['boolean' => Money::fromDecimal('usd', 0.02)],
            ['boolean' => 1.01],
            ['integer' => 'xxx'],
            ['integer' => Money::fromDecimal('usd', 0.02)],
            ['jsonMoney' => 'xxx'],
            ['jsonMoney' => 1.01],
            ['jsonMoney' => 1],
            ['decimal' => 'xxx'],
            ['decimal' => Money::fromDecimal('usd', 0.02)],
        ];

        foreach ($metadatas as $key => $meta) {
            self::$payment->metadata = (object) $meta;
            try {
                self::$payment->saveOrFail();
                $this->assertTrue(false, "No exception thrown $key");
            } catch (ModelException $e) {
            }
        }
    }

    public function testReuseTransactionIds(): void
    {
        $customer = new Customer();
        $customer->name = 'INVD-2639';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 500]];
        $invoice->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer($customer);
        $invoice2->items = [['unit_cost' => 50]];
        $invoice2->saveOrFail();

        $appliedToSet = [
            [
                'type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 500,
            ],
            [
                'type' => 'invoice',
                'invoice' => $invoice2,
                'amount' => 50,
            ],
            [
                'type' => 'credit',
                'amount' => 50,
            ],
            [
                'type' => 'convenience_fee',
                'amount' => 25,
            ],
        ];

        $payment = new Payment();
        $payment->setCustomer($customer);
        $payment->amount = 625;
        $payment->applied_to = $appliedToSet;
        $payment->saveOrFail();

        $originalAppliedTo = $payment->applied_to;

        $payment->applied_to = $appliedToSet;
        $payment->saveOrFail();
        // everything in the applied_to value should be unchanged,
        // including the transaction IDs
        $this->assertEquals($originalAppliedTo, $payment->applied_to);
    }

    public function testCleanUpTransactions(): void
    {
        self::hasInvoice();
        $payment = new Payment();
        $payment->amount = self::$invoice->balance;
        $payment->applied_to = [['invoice' => self::$invoice, 'type' => 'invoice', 'amount' => self::$invoice->balance]];
        $payment->saveOrFail();

        $transactions = Transaction::where('payment_id', $payment->id)->execute();
        $this->assertCount(1, $transactions);
        $payment->delete();
        $this->assertEquals(0, Transaction::where('payment_id', $payment->id)->count());
        $this->assertNull(Transaction::find($transactions[0]->id));
    }

    /**
     * INVD-2778.
     *
     * Tests that the parent_transaction value on child transactions is updated
     * when the parent transaction is removed from the payment.
     */
    public function testParentTransaction(): void
    {
        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer);
        $invoice1->items = [['unit_cost' => 100, 'quantity' => 1]];
        $invoice1->saveOrFail();
        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [['unit_cost' => 100, 'quantity' => 1]];
        $invoice2->saveOrFail();
        $invoice3 = new Invoice();
        $invoice3->setCustomer(self::$customer);
        $invoice3->items = [['unit_cost' => 100, 'quantity' => 1]];
        $invoice3->saveOrFail();
        $invoice4 = new Invoice();
        $invoice4->setCustomer(self::$customer);
        $invoice4->items = [['unit_cost' => 100, 'quantity' => 1]];
        $invoice4->saveOrFail();

        $payment = new Payment();
        $payment->amount = 40;
        $payment->applied_to = [
            ['invoice' => $invoice1, 'type' => 'invoice', 'amount' => 10],
            ['invoice' => $invoice2, 'type' => 'invoice', 'amount' => 10],
            ['invoice' => $invoice3, 'type' => 'invoice', 'amount' => 10],
            ['invoice' => $invoice4, 'type' => 'invoice', 'amount' => 10],
        ];
        $payment->saveOrFail();

        /** @var Transaction[] $transactions */
        $transactions = Transaction::where('payment_id', $payment->id())->all()
            ->toArray();
        $this->assertNull($transactions[0]->parent_transaction);
        $this->assertEquals($transactions[0]->id(), $transactions[1]->parent_transaction);
        $this->assertEquals($transactions[0]->id(), $transactions[2]->parent_transaction);
        $this->assertEquals($transactions[0]->id(), $transactions[3]->parent_transaction);

        // remove parent transaction
        $payment->applied_to = [
            ['invoice' => $invoice2, 'type' => 'invoice', 'amount' => 10],
            ['invoice' => $invoice3, 'type' => 'invoice', 'amount' => 10],
            ['invoice' => $invoice4, 'type' => 'invoice', 'amount' => 10],
        ];
        $payment->saveOrFail();

        // assert that the first parent transaction was deleted
        $oldParentId = (int) $transactions[0]->id();
        $oldParent = Transaction::where('id', $oldParentId)->oneOrNull();
        $this->assertNull($oldParent);

        // assert that the parent_transaction values have been updated
        /** @var Transaction[] $transactions */
        $transactions = Transaction::where('payment_id', $payment->id())->all()
            ->toArray();
        $this->assertNull($transactions[0]->parent_transaction);
        $this->assertNotEquals($oldParentId, $transactions[1]->parent_transaction);
        $this->assertEquals($transactions[0]->id(), $transactions[1]->parent_transaction);
        $this->assertNotEquals($oldParentId, $transactions[2]->parent_transaction);
        $this->assertEquals($transactions[0]->id(), $transactions[2]->parent_transaction);
    }

    public function testInvd2904(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [['unit_cost' => 100]];
        $creditNote->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 500;
        $payment->applied_to = [
            [
                'type' => 'credit_note',
                'document_type' => 'invoice',
                'invoice' => $invoice,
                'credit_note' => $creditNote,
                'amount' => 100,
            ],
        ];
        $payment->saveOrFail();
        $this->assertTrue($invoice->paid);

        $payment->void();

        $this->assertFalse($invoice->refresh()->paid);
    }

    /**
     * Tests a payment which has multiple credit notes applied
     * to a single invoice.
     */
    public function testApplyCreditNotesToInvoice(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 20]];
        $invoice->saveOrFail();

        $creditNote1 = new CreditNote();
        $creditNote1->setCustomer(self::$customer);
        $creditNote1->items = [['unit_cost' => 10]];
        $creditNote1->saveOrFail();

        $creditNote2 = new CreditNote();
        $creditNote2->setCustomer(self::$customer);
        $creditNote2->items = [['unit_cost' => 10]];
        $creditNote2->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 0;
        $payment->applied_to = [
            [
                'type' => 'credit_note',
                'document_type' => 'invoice',
                'invoice' => $invoice,
                'credit_note' => $creditNote1->id(),
                'amount' => 10,
            ],
            [
                'type' => 'credit_note',
                'document_type' => 'invoice',
                'invoice' => $invoice,
                'credit_note' => $creditNote2->id(),
                'amount' => 10,
            ],
        ];

        $payment->saveOrFail();
        $this->assertTrue($invoice->paid);
        $this->assertEquals(0, $invoice->balance);

        $payment->void();
    }

    public function testInvalidCurrency(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'eur';
        $invoice->items = [['unit_cost' => 20]];
        $invoice->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 20;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 20,
            ],
        ];

        $this->assertFalse($payment->save());
        $this->assertEquals('An error occurred while trying to apply payment: The currency of invoice '.$invoice->number.' (eur) does not match the payment currency (usd)', (string) $payment->getErrors());
    }

    public function testReferenceSameInvoiceTwiceId(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [['unit_cost' => 100]];
        $creditNote->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 100;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice->id,
                'amount' => 100,
            ],
            [
                'type' => 'credit_note',
                'credit_note' => $creditNote->id,
                'document_type' => 'invoice',
                'invoice' => $invoice->id,
                'amount' => 100,
            ],
        ];
        $payment->saveOrFail();

        $this->assertEquals(0, $invoice->refresh()->balance);
        $this->assertEquals(0, $creditNote->refresh()->balance);
    }

    public function testReferenceSameInvoiceTwiceModel(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [['unit_cost' => 100]];
        $creditNote->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 100;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => Invoice::findOrFail($invoice->id),
                'amount' => 100,
            ],
            [
                'type' => 'credit_note',
                'credit_note' => CreditNote::findOrFail($creditNote->id),
                'document_type' => 'invoice',
                'invoice' => Invoice::findOrFail($invoice->id),
                'amount' => 100,
            ],
        ];
        $payment->saveOrFail();

        $this->assertEquals(0, $invoice->refresh()->balance);
        $this->assertEquals(0, $creditNote->refresh()->balance);
    }

    public function testEditCreditNoteApplication(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [['unit_cost' => 100]];
        $creditNote->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->applied_to = [
            [
                'type' => 'credit_note',
                'credit_note' => $creditNote,
                'document_type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 50,
            ],
        ];
        $payment->saveOrFail();

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

        $this->assertEquals(100, $invoice->refresh()->balance);
        $this->assertEquals(0, $creditNote->refresh()->balance);
    }

    public function testGetAppliedToLimits(): void
    {
        self::hasCustomer();
        self::hasPayment();
        $invoices = [];
        for ($i = 0; $i < 200; ++$i) {
            $invoice = new Invoice();
            $invoice->setCustomer(self::$customer);
            $invoice->items = [
                [
                    'name' => 'Test Item',
                    'description' => 'test',
                    'quantity' => 1,
                    'unit_cost' => 1,
                ],
            ];
            $invoice->saveOrFail();
            $invoices[] = $invoice;
        }

        $splits = array_map(fn ($invoice) => [
            'type' => PaymentItemType::Invoice->value,
            'invoice' => $invoice->id(),
            'amount' => 1.0,
        ], $invoices);

        self::$payment->applied_to = $splits;
        self::$payment->saveOrFail();

        $this->assertCount(200, self::$payment->getTransactions());
        $this->assertEquals(200, Transaction::where('customer', self::$customer->id)->count());

        self::$payment->void();
        $this->assertEquals(0, Transaction::where('customer', self::$customer->id)->count());
    }

    public function testChangeCurrency(): void
    {
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->currency = 'usd';
        $payment->amount = 100;
        $payment->saveOrFail();

        $payment->currency = 'eur';
        $this->assertTrue($payment->save());

        $payment->applied_to = [
            [
                'type' => PaymentItemType::Credit->value,
                'amount' => 100,
            ],
        ];
        $payment->saveOrFail();

        $payment->currency = 'usd';
        $this->assertFalse($payment->save());
        $this->assertEquals('The currency cannot be modified if the payment is applied. You must first unapply the payment to change the currency.', (string) $payment->getErrors());
    }
}
