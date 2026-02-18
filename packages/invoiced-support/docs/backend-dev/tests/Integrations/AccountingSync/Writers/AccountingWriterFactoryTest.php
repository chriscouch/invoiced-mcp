<?php

namespace App\Tests\Integrations\AccountingSync\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Writers\AccountingWriterFactory;
use App\Integrations\AccountingSync\Writers\NullWriter;
use App\Integrations\BusinessCentral\Writers\BusinessCentralPaymentWriter;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Writers\IntacctArInvoiceWriter;
use App\Integrations\Intacct\Writers\IntacctCreditNoteWriter;
use App\Integrations\Intacct\Writers\IntacctCustomerWriter;
use App\Integrations\Intacct\Writers\IntacctOrderEntryInvoiceWriter;
use App\Integrations\Intacct\Writers\IntacctPaymentWriter;
use App\Integrations\NetSuite\Writers\NetSuiteWriter;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksCreditNoteWriter;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksCustomerWriter;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksInvoiceWriter;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksPaymentWriter;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Xero\Writers\XeroCreditNoteWriter;
use App\Integrations\Xero\Writers\XeroCustomerWriter;
use App\Integrations\Xero\Writers\XeroInvoiceWriter;
use App\Integrations\Xero\Writers\XeroPaymentWriter;
use App\Tests\AppTestCase;

class AccountingWriterFactoryTest extends AppTestCase
{
    private static IntacctSyncProfile $intacctSyncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        self::hasOAuthAccount(IntegrationType::BusinessCentral);
        self::hasAccountingSyncProfile(IntegrationType::BusinessCentral);

        self::hasIntacctAccount();
        self::$intacctSyncProfile = new IntacctSyncProfile();
        self::$intacctSyncProfile->saveOrFail();

        self::hasNetSuiteAccount();
        self::hasAccountingSyncProfile(IntegrationType::NetSuite);

        self::hasQuickBooksAccount();
        $qboSyncProfile = new QuickBooksOnlineSyncProfile();
        $qboSyncProfile->saveOrFail();

        self::hasXeroAccount();
        $xeroSyncProfile = new XeroSyncProfile();
        $xeroSyncProfile->saveOrFail();
    }

    public function writerProvider(): array
    {
        return [
            ['business_central', Payment::class, BusinessCentralPaymentWriter::class],
            ['intacct', CreditNote::class, IntacctCreditNoteWriter::class],
            ['intacct', Customer::class, IntacctCustomerWriter::class],
            ['intacct', Payment::class, IntacctPaymentWriter::class],
            ['intacct', Transaction::class, NullWriter::class],
            ['netsuite', Transaction::class, NetSuiteWriter::class],
            ['quickbooks_desktop', Customer::class, NullWriter::class],
            ['quickbooks_online', CreditNote::class, QuickBooksCreditNoteWriter::class],
            ['quickbooks_online', Customer::class, QuickBooksCustomerWriter::class],
            ['quickbooks_online', Invoice::class, QuickBooksInvoiceWriter::class],
            ['quickbooks_online', Payment::class, QuickBooksPaymentWriter::class],
            ['quickbooks_online', Transaction::class, NullWriter::class],
            ['xero', CreditNote::class, XeroCreditNoteWriter::class],
            ['xero', Customer::class, XeroCustomerWriter::class],
            ['xero', Invoice::class, XeroInvoiceWriter::class],
            ['xero', Payment::class, XeroPaymentWriter::class],
        ];
    }

    private function getFactory(): AccountingWriterFactory
    {
        return self::getService('test.accounting_writer_factory');
    }

    public function testBuildIntacctInvoices(): void
    {
        self::$intacctSyncProfile->write_to_order_entry = false;
        self::$intacctSyncProfile->saveOrFail();
        $this->performTest('intacct', Invoice::class, IntacctArInvoiceWriter::class);
        self::$intacctSyncProfile->write_to_order_entry = true;
        self::$intacctSyncProfile->saveOrFail();
        $this->performTest('intacct', Invoice::class, IntacctOrderEntryInvoiceWriter::class);
    }

    /**
     * @dataProvider writerProvider
     */
    public function testBuild(string $accountingSystem, string $modelClass, string $expectedWriterClass): void
    {
        $this->performTest($accountingSystem, $modelClass, $expectedWriterClass);
    }

    private function performTest(string $accountingSystem, string $modelClass, string $expectedWriterClass): void
    {
        /** @var AccountingWritableModelInterface $obj */
        $obj = new $modelClass();
        $obj->tenant_id = self::$company->id; /* @phpstan-ignore-line */

        $factory = $this->getFactory();
        $integrationId = IntegrationType::fromString($accountingSystem);
        $writer = $factory->build($obj, $integrationId);
        $this->assertInstanceOf($expectedWriterClass, $writer); /* @phpstan-ignore-line */
    }
}
