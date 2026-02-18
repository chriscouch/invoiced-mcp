<?php

namespace App\Tests\Integrations\NetSuite\Libs;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Note;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\NetSuite\Writers\AbstractNetSuiteTransactionWriter;
use App\Integrations\NetSuite\Writers\NetSuiteCreditNoteWriter;
use App\Integrations\NetSuite\Writers\NetSuiteCustomerWriter;
use App\Integrations\NetSuite\Writers\NetSuiteInvoiceWriter;
use App\Integrations\NetSuite\Writers\NetSuiteNoteWriter;
use App\Integrations\NetSuite\Writers\NetSuitePaymentWriter;
use App\Integrations\NetSuite\Writers\NetSuiteWriter;
use App\Integrations\NetSuite\Writers\NetSuiteWriterFactory;
use App\Integrations\NetSuite\Writers\NullWriterNetSuite;
use App\Tests\AppTestCase;

class NetSuiteWriterFactoryTest extends AppTestCase
{
    private static NetSuiteWriterFactory $factory;

    public static function setUpBeforeClass(): void
    {
        self::hasCompany();
        self::$factory = new NetSuiteWriterFactory();
    }

    public function testNote(): void
    {
        $adapter = self::$factory->create(new Note(), new AccountingSyncProfile());
        $this->assertInstanceOf(NetSuiteNoteWriter::class, $adapter);
        $this->assertTrue($adapter->shouldCreate());
        $this->assertTrue($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());
    }

    public function testTransaction(): void
    {
        $transaction = new Transaction();
        $adapter = self::$factory->create($transaction, new AccountingSyncProfile());
        $this->assertInstanceOf(AbstractNetSuiteTransactionWriter::class, $adapter);
        $this->assertTrue($adapter->shouldCreate());
        $this->assertFalse($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());

        $transaction->parent_transaction = 1;
        $this->assertFalse($adapter->shouldCreate());
        $this->assertFalse($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());

        $transaction->metadata = (object) [NetSuiteWriter::REVERSE_MAPPING => 1];
        $this->assertFalse($adapter->shouldCreate());
        $this->assertFalse($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());

        $transaction->parent_transaction = 0;
        $this->assertFalse($adapter->shouldCreate());
        $this->assertFalse($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());
    }

    public function testPayment(): void
    {
        $payment = new Payment();
        $adapter = self::$factory->create($payment, new AccountingSyncProfile());
        $this->assertInstanceOf(NetSuitePaymentWriter::class, $adapter);
        $this->assertTrue($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());
    }

    public function testCustomer(): void
    {
        $profile = new AccountingSyncProfile();
        $customer = new Customer();
        $adapter = self::$factory->create($customer, $profile);
        $this->assertInstanceOf(NullWriterNetSuite::class, $adapter);

        $profile->write_customers = true;
        $adapter = self::$factory->create($customer, $profile);
        $this->assertInstanceOf(NetSuiteCustomerWriter::class, $adapter);
        $this->assertTrue($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());
    }

    public function testInvoice(): void
    {
        $profile = new AccountingSyncProfile();
        $invoice = new Invoice();
        $adapter = self::$factory->create($invoice, $profile);
        $this->assertInstanceOf(NullWriterNetSuite::class, $adapter);

        $profile->write_invoices = true;
        $adapter = self::$factory->create($invoice, $profile);
        $this->assertInstanceOf(NetSuiteInvoiceWriter::class, $adapter);
        $this->assertTrue($adapter->shouldCreate());
        $this->assertTrue($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());

        $invoice->draft = true;
        $this->assertInstanceOf(NetSuiteInvoiceWriter::class, $adapter);
        $this->assertFalse($adapter->shouldCreate());
        $this->assertFalse($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());

        $invoice->draft = false;
        $invoice->metadata = (object) [NetSuiteWriter::REVERSE_MAPPING => 1];
        $adapter = self::$factory->create($invoice, $profile);
        $this->assertInstanceOf(NetSuiteInvoiceWriter::class, $adapter);
        $this->assertFalse($adapter->shouldCreate());
        $this->assertFalse($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());
    }

    public function testCreditNote(): void
    {
        $profile = new AccountingSyncProfile();
        $creditNote = new CreditNote();
        $adapter = self::$factory->create($creditNote, $profile);
        $this->assertInstanceOf(NullWriterNetSuite::class, $adapter);

        $profile->write_credit_notes = true;
        $adapter = self::$factory->create($creditNote, $profile);
        $this->assertInstanceOf(NetSuiteCreditNoteWriter::class, $adapter);
        $this->assertTrue($adapter->shouldCreate());
        $this->assertTrue($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());

        $creditNote->draft = true;
        $this->assertInstanceOf(NetSuiteCreditNoteWriter::class, $adapter);
        $this->assertFalse($adapter->shouldCreate());
        $this->assertFalse($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());

        $creditNote->draft = false;
        $creditNote->metadata = (object) [NetSuiteWriter::REVERSE_MAPPING => 1];
        $adapter = self::$factory->create($creditNote, $profile);
        $this->assertInstanceOf(NetSuiteCreditNoteWriter::class, $adapter);
        $this->assertFalse($adapter->shouldCreate());
        $this->assertFalse($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());
    }

    public function testNull(): void
    {
        $invoice = new Invoice();
        $adapter = self::$factory->create($invoice, new AccountingSyncProfile());
        $this->assertInstanceOf(NullWriterNetSuite::class, $adapter);
        $this->assertFalse($adapter->shouldCreate());
        $this->assertFalse($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());

        $invoice->metadata = (object) [NetSuiteWriter::REVERSE_MAPPING => 1];
        $this->assertFalse($adapter->shouldCreate());
        $this->assertFalse($adapter->shouldUpdate());
        $this->assertFalse($adapter->shouldDelete());
    }

    /**
     * @dataProvider transactionStartProvider
     */
    public function testTransactionDate(?int $transactionStartTime, bool $expected): void
    {
        $invoice = new Invoice();
        $invoice->date = strtotime('2021-01-01 10:00:00');
        $profile = new AccountingSyncProfile();
        $profile->write_invoices = true;
        $profile->invoice_start_date = $transactionStartTime;
        $adapter = self::$factory->create($invoice, $profile);
        $this->assertInstanceOf(NetSuiteInvoiceWriter::class, $adapter);

        $this->assertEquals($expected, $adapter->shouldCreate());
        $this->assertEquals($expected, $adapter->shouldUpdate());
    }

    public function transactionStartProvider(): array
    {
        return [
            [null, true],
            [strtotime('2020-01-01 10:00:00'), true],
            [strtotime('2021-01-01 11:00:00'), true],
            [strtotime('2021-01-02 00:00:00'), false],
        ];
    }
}
