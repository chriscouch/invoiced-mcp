<?php

namespace App\Tests\Integrations\AccountingSync\Loaders;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\CashApplication\Models\Payment;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Core\I18n\ValueObjects\Money;
use App\Core\LockFactoryFacade;
use App\Core\Utils\ModelLock;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Loaders\AccountingInvoiceLoader;
use App\Integrations\AccountingSync\Models\AccountingConvenienceFeeMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\Enums\IntegrationType;
use App\Sending\Models\ScheduledSend;
use App\Tests\AppTestCase;

class AccountingInvoiceLoaderTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    private function getLoader(): AccountingInvoiceLoader
    {
        return self::getService('test.accounting_invoice_loader');
    }

    private function createCadence(int $hour = 4): InvoiceChasingCadence
    {
        $cadence = new InvoiceChasingCadence();
        $cadence->name = 'Chasing Cadence';
        $cadence->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => $hour,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];
        $cadence->save();

        return $cadence;
    }

    public function testLoadSourceFromInvoiced(): void
    {
        $mapping = new AccountingInvoiceMapping();
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '0891234';
        $mapping->invoice = self::$invoice;
        $mapping->source = AccountingInvoiceMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $record = new AccountingInvoice(
            integration: IntegrationType::Intacct,
            accountingId: '0891234',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '1234',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            values: [
                'number' => self::$invoice->number,
                'currency' => 'usd',
                'items' => [['unit_cost' => 500]],
            ],
        );

        $loader = $this->getLoader();
        $result = $loader->load($record);

        $this->assertNotNull($result->getModel());
        $this->assertNull($result->getAction());

        $this->assertEquals(self::$invoice->id(), $result->getModel()?->id());

        // should NOT update the mapping source
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->refresh()->integration_id);
        $this->assertEquals('0891234', $mapping->accounting_id);
        $this->assertEquals(AccountingCustomerMapping::SOURCE_INVOICED, $mapping->source);
    }

    public function testLoadSourceFromAccountingSystem(): void
    {
        $cadence = $this->createCadence();
        $record = new AccountingInvoice(
            integration: IntegrationType::Intacct,
            accountingId: '0891235',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '1234',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            values: [
                'number' => self::$invoice->number,
                'currency' => 'usd',
                'items' => [['unit_cost' => 500]],
            ],
            delivery: [
                'emails' => 'test@test.com',
                'cadence_id' => $cadence->id,
            ],
        );

        $loader = $this->getLoader();
        $result = $loader->load($record);

        $delivery = InvoiceDelivery::where('invoice_id', $result->getModel()?->id())->oneOrNull();
        $this->assertNotNull($delivery);
        /* @var InvoiceDelivery $delivery */
        $this->assertEquals('test@test.com', $delivery->emails);
        $this->assertCount(1, $delivery->chase_schedule);
        $this->assertEquals([
            'hour' => 4,
            'email' => true,
            'sms' => false,
            'letter' => false,
        ], $delivery->chase_schedule[0]['options']);
        $this->assertEquals(InvoiceChasingCadence::ON_ISSUE, $delivery->chase_schedule[0]['trigger']);

        // INVD-3185
        $cadence = $this->createCadence(5);
        $record = new AccountingInvoice(
            integration: IntegrationType::Intacct,
            accountingId: '0891235',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '1234',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            values: [
                'number' => self::$invoice->number,
                'currency' => 'usd',
                'items' => [['unit_cost' => 500]],
            ],
            delivery: [
                'emails' => 'test@test.com',
                'cadence_id' => $cadence->id,
            ],
        );
        $send = new ScheduledSend();
        $send->invoice_id = self::$invoice->id;
        $send->sent = true;
        $send->reference = InvoiceDelivery::getSendReference($delivery, $delivery->getChaseSchedule()->current());
        $send->saveOrFail();

        // No exception should be thrown
        $this->assertNotNull($loader->load($record));
    }

    public function testLoadDeletedInvoice(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $mapping = new AccountingInvoiceMapping();
        $mapping->invoice = $invoice;
        $mapping->integration_id = IntegrationType::QuickBooksOnline->value;
        $mapping->accounting_id = 'delete_1';
        $mapping->source = AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        $accountingInvoice = new AccountingInvoice(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'delete_1',
            deleted: true
        );

        $loader = $this->getLoader();
        $result = $loader->load($accountingInvoice);

        // should delete the invoice
        $this->assertTrue($result->wasDeleted());
        $this->assertNotNull($result->getModel());
        $this->assertNull(Invoice::find($invoice->id()));

        $accountingInvoice2 = new AccountingInvoice(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'delete_2',
            deleted: true
        );

        $result = $loader->load($accountingInvoice2);

        // should do nothing because the invoice is not existing
        $this->assertNull($result->getAction());
        $this->assertNull($result->getModel());
    }

    public function testLoadConvenienceFeeInvoice(): void
    {
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 100;
        $payment->applied_to = [['type' => 'convenience_fee', 'amount' => 100]];
        $payment->saveOrFail();

        $mapping = new AccountingConvenienceFeeMapping();
        $mapping->source = AccountingConvenienceFeeMapping::SOURCE_INVOICED;
        $mapping->integration_id = IntegrationType::QuickBooksOnline->value;
        $mapping->payment = $payment;
        $mapping->accounting_id = 'conv_fee_test';
        $mapping->saveOrFail();

        $accountingInvoice = new AccountingInvoice(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'conv_fee_test',
        );

        $loader = $this->getLoader();
        $result = $loader->load($accountingInvoice);

        // should do nothing because the invoice is already mapped
        $this->assertNull($result->getAction());
        $this->assertNull($result->getModel());
    }

    public function testUpdateMappingInfo(): void
    {
        $customer = self::getTestDataFactory()->createCustomer();
        $invoice = self::getTestDataFactory()->createInvoice($customer);
        $creditNote = self::getTestDataFactory()->createCreditNote($customer);
        self::getTestDataFactory()->createCreditNoteTransaction($creditNote, $invoice);

        $accountingInvoice = new AccountingInvoice(
            integration: IntegrationType::NetSuite,
            accountingId: '654236',
            values: [
                'number' => $invoice->number,
            ],
        );
        $this->assertNull(AccountingInvoiceMapping::find($invoice->id));

        $loader = $this->getLoader();
        $loader->load($accountingInvoice);

        $mapping = AccountingInvoiceMapping::findOrFail($invoice->id)->toArray();
        unset($mapping['created_at']);
        unset($mapping['updated_at']);
        $this->assertEquals([
            'invoice_id' => $invoice->id,
            'accounting_id' => '654236',
            'source' => 'accounting_system',
            'integration_name' => 'NetSuite',
        ], $mapping);
    }

    public function testAutoPayPaymentTermsNotOverwritten(): void
    {
        $invoice = self::getTestDataFactory()->createInvoice(self::$customer);
        $invoice->autopay = true;
        $invoice->saveOrFail();

        $record = new AccountingInvoice(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: '9649849',
            customer: new AccountingCustomer(
                integration: IntegrationType::QuickBooksOnline,
                accountingId: '1234',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            values: [
                'number' => $invoice->number,
                'currency' => 'usd',
                'items' => [['unit_cost' => 100]],
                'payment_terms' => 'NET 30',
            ],
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals('AutoPay', $invoice->refresh()->payment_terms);
    }

    public function testLoadSyncBalanceMatches(): void
    {
        // Scenario: Matching balance with no payment applied
        $factory = self::getTestDataFactory();
        $invoice = $factory->createInvoice(self::$customer);

        $record = new AccountingInvoice(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: new AccountingCustomer(
                integration: IntegrationType::BusinessCentral,
                accountingId: '1234',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            values: [
                'number' => $invoice->number,
                'currency' => 'usd',
                'items' => [['unit_cost' => 100]],
            ],
            balance: new Money('usd', 10000),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(100, $invoice->refresh()->balance);
        $this->assertEquals(0, $invoice->amount_paid);
    }

    public function testLoadSyncBalance(): void
    {
        // Scenario: Initial balance sync
        $factory = self::getTestDataFactory();
        $invoice = $factory->createInvoice(self::$customer);

        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::BusinessCentral,
            accountingId: '1234',
            values: [
                'name' => self::$customer->name,
            ],
        );
        $recordValues = [
            'number' => $invoice->number,
            'currency' => 'usd',
            'items' => [['unit_cost' => 100]],
        ];
        $record = new AccountingInvoice(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: $accountingCustomer,
            values: $recordValues,
            balance: new Money('usd', 9900),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(99, $invoice->refresh()->balance);
        $this->assertEquals(1, $invoice->amount_paid);

        // Scenario: Balance decreases after initial sync
        $record = new AccountingInvoice(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: $accountingCustomer,
            values: $recordValues,
            balance: new Money('usd', 8000),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(80, $invoice->refresh()->balance);
        $this->assertEquals(20, $invoice->amount_paid);

        // Scenario: Balance increases after previous sync
        $record = new AccountingInvoice(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: $accountingCustomer,
            values: $recordValues,
            balance: new Money('usd', 9000),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(90, $invoice->refresh()->balance);
        $this->assertEquals(10, $invoice->amount_paid);

        // Scenario: Mismatched balance with payment applied
        $payment = $factory->createPayment(self::$customer);
        $payment->applied_to = [['type' => 'invoice', 'invoice' => $invoice, 'amount' => 30]];
        $payment->saveOrFail();
        $record = new AccountingInvoice(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: $accountingCustomer,
            values: $recordValues,
            balance: new Money('usd', 8000),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(60, $invoice->refresh()->balance);
        $this->assertEquals(40, $invoice->amount_paid);

        // Scenario: Paid in full with no other payments applied
        $payment->deleteOrFail();
        $record = new AccountingInvoice(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: $accountingCustomer,
            values: $recordValues,
            balance: new Money('usd', 0),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(0, $invoice->refresh()->balance);
        $this->assertEquals(100, $invoice->amount_paid);
        $this->assertTrue($invoice->paid);
    }


    public function testLoadMetadata(): void
    {
        $invoice = self::getTestDataFactory()->createInvoice(self::$customer);
        $invoice->metadata = (object) [
            'key1' => 'value1',
            'key2' => 'value1',
            'key3' => 'value1',
            'key4' => 'value1',
            'key5' => 'value1',
            'key6' => 'value1',
            'key7' => 'value1',
            'key8' => 'value1',
            'key9' => 'value1',
            'key10' => 'value1',
        ];
        $invoice->saveOrFail();

        $record = $this->makeRecord($invoice);

        $loader = $this->getLoader();
        try {
            $loader->load($record);
            $this->fail('LoadException should not be thrown');
        } catch (LoadException $e) {
            $this->assertEquals('Could not update Invoice: There can only be up to 10 metadata values. 11 values were provided.', $e->getMessage());
        }

        //unlock
        $invoice = self::getTestDataFactory()->createInvoice(self::$customer);
        $invoice->metadata = (object) [
            'key1' => 'value1',
            'key2' => 'value1',
            'key3' => 'value1',
            'key4' => 'value1',
            'key5' => 'value1',
            'key6' => '',
            'key7' => 'value1',
            'key8' => 'value1',
            'key9' => 'value1',
            'key10' => 'value1',
        ];
        $invoice->saveOrFail();
        $record = $this->makeRecord($invoice);
        $loader->load($record);
        $invoice = Invoice::findOrFail($invoice->id());
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value1',
            'key3' => 'value1',
            'key4' => 'value1',
            'key5' => 'value1',
            'key7' => 'value1',
            'key8' => 'value1',
            'key9' => 'value1',
            'key10' => 'value1',
            'new_key1' => 'value1',
        ], (array) $invoice->metadata);
    }

    private function makeRecord(Invoice $invoice): AccountingInvoice
    {
        return new AccountingInvoice(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: (string) microtime(true),
            customer: new AccountingCustomer(
                integration: IntegrationType::QuickBooksOnline,
                accountingId: '1234',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            values: [
                'number' => $invoice->number,
                'currency' => 'usd',
                'items' => [['unit_cost' => 100]],
                'metadata' => [
                    'new_key1' => 'value1',
                    'new_key2' => '',
                ],
            ],
        );
    }
}
