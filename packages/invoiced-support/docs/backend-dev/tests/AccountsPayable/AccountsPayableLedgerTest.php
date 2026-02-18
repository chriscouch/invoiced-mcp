<?php

namespace App\Tests\AccountsPayable;

use App\AccountsPayable\Enums\ApAccounts;
use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\VendorCredit;
use App\Companies\Models\Company;
use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Ledger;
use App\Core\Ledger\ValueObjects\AccountingVendor;
use App\Core\Ledger\ValueObjects\Credit;
use App\Core\Ledger\ValueObjects\Debit;
use App\Core\Ledger\ValueObjects\Document;
use App\Core\Ledger\ValueObjects\LedgerEntry;
use App\Core\Ledger\ValueObjects\Transaction;
use App\Reports\ValueObjects\AgingBreakdown;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Money\Currency;
use Money\Money;

class AccountsPayableLedgerTest extends AppTestCase
{
    private static Company $company2;
    private bool $populated = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::hasCompany();
        $connection = self::getTestDataFactory()->connectCompanies(self::$company2, self::$company);
        self::hasVendor();
        self::$vendor->network_connection = $connection;
        self::$vendor->saveOrFail();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function getLedger(): AccountsPayableLedger
    {
        return self::getService('test.accounts_payable_ledger');
    }

    public function testSyncBill(): void
    {
        $apLedger = $this->getLedger();
        $ledger = $apLedger->getLedger(self::$company);

        // Start at PendingApproval
        $bill = new Bill();
        $bill->vendor = self::$vendor;
        $bill->date = new CarbonImmutable('2009-12-15');
        $bill->number = 'INV-00001';
        $bill->currency = 'usd';
        $bill->total = 1000;
        $bill->status = PayableDocumentStatus::PendingApproval;
        $bill->saveOrFail();
        $vendor = new AccountingVendor(self::$vendor->id);
        $apLedger->syncBill($ledger, $bill);
        $documentId = $ledger->documents->getOrCreate(new Document(
            type: DocumentType::Invoice,
            reference: (string) $bill->id,
            party: $vendor,
            date: CarbonImmutable::now(),
        ));

        // Check vendor and document balance
        $this->assertEquals(new Money(-100000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(-100000, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-100000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));

        // Transition to Approved
        $bill->status = PayableDocumentStatus::Approved;
        $bill->saveOrFail();
        $apLedger->syncBill($ledger, $bill);
        // Check vendor and document balance
        $this->assertEquals(new Money(-100000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(-100000, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-100000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));

        // Modify the document date and amount
        $bill->date = new CarbonImmutable('2009-12-10');
        $bill->total = 2000;
        $bill->saveOrFail();
        $apLedger->syncBill($ledger, $bill);
        $this->assertEquals(new Money(-200000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(-200000, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-10',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-200000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));

        // Transition to Paid
        $bill->status = PayableDocumentStatus::Paid;
        $bill->saveOrFail();
        $apLedger->syncBill($ledger, $bill);
        // Check vendor and document balance
        $this->assertEquals(new Money(-200000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(-200000, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-10',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-200000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));

        // Transition to Voided
        $bill->voided = true;
        $bill->saveOrFail();
        $apLedger->syncBill($ledger, $bill);
        // Check vendor and document balance
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));
    }

    public function testSyncVendorCredit(): void
    {
        $apLedger = $this->getLedger();
        $ledger = $apLedger->getLedger(self::$company);

        // Start at PendingApproval
        $vendorCredit = new VendorCredit();
        $vendorCredit->vendor = self::$vendor;
        $vendorCredit->date = new CarbonImmutable('2009-12-15');
        $vendorCredit->number = 'CN-00001';
        $vendorCredit->currency = 'usd';
        $vendorCredit->total = 1000;
        $vendorCredit->status = PayableDocumentStatus::PendingApproval;
        $vendorCredit->saveOrFail();
        $vendor = new AccountingVendor(self::$vendor->id);
        $apLedger->syncVendorCredit($ledger, $vendorCredit);
        $documentId = $ledger->documents->getOrCreate(new Document(
            type: DocumentType::CreditNote,
            reference: (string) $vendorCredit->id,
            party: $vendor,
            date: CarbonImmutable::now(),
        ));

        // Check vendor and document balance
        $this->assertEquals(new Money(100000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(100000, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'CreditNote',
                'reference' => (string) $vendorCredit->id,
                'amount' => new Money(100000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));

        // Transition to Approved
        $vendorCredit->status = PayableDocumentStatus::Approved;
        $vendorCredit->saveOrFail();
        $apLedger->syncVendorCredit($ledger, $vendorCredit);
        // Check vendor and document balance
        $this->assertEquals(new Money(100000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(100000, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'CreditNote',
                'reference' => (string) $vendorCredit->id,
                'amount' => new Money(100000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));

        // Modify the document date and amount
        $vendorCredit->date = new CarbonImmutable('2009-12-10');
        $vendorCredit->total = 2000;
        $vendorCredit->saveOrFail();
        $apLedger->syncVendorCredit($ledger, $vendorCredit);
        // Check vendor and document balance
        $this->assertEquals(new Money(200000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(200000, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-10',
                'document_type' => 'CreditNote',
                'reference' => (string) $vendorCredit->id,
                'amount' => new Money(200000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));

        // Transition to Voided
        $vendorCredit->voided = true;
        $vendorCredit->saveOrFail();
        $apLedger->syncVendorCredit($ledger, $vendorCredit);
        // Check vendor and document balance
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));
    }

    public function testSyncPayment(): void
    {
        $apLedger = $this->getLedger();
        $ledger = $apLedger->getLedger(self::$company);
        $createOp = self::getService('test.vendor_payment_create');
        $editOp = self::getService('test.vendor_payment_edit');
        $voidOp = self::getService('test.vendor_payment_void');

        // Create an invoice
        $bill = new Bill();
        $bill->vendor = self::$vendor;
        $bill->date = new CarbonImmutable('2009-12-15');
        $bill->number = 'INV-00003';
        $bill->currency = 'usd';
        $bill->total = 100;
        $bill->status = PayableDocumentStatus::Approved;
        $bill->saveOrFail();
        $vendor = new AccountingVendor(self::$vendor->id);
        $apLedger->syncBill($ledger, $bill);
        $invoiceDocumentId = $ledger->documents->getOrCreate(new Document(
            type: DocumentType::Invoice,
            reference: (string) $bill->id,
            party: $vendor,
            date: CarbonImmutable::now(),
        ));

        $this->assertEquals(new Money(-10000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(-10000, new Currency('USD')), $ledger->reporting->getDocumentBalance($invoiceDocumentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-10000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($invoiceDocumentId, ApAccounts::AccountsPayable->value));

        // Create a payment
        $payment = $createOp->create([
            'vendor' => self::$vendor,
            'currency' => 'usd',
            'amount' => 100,
            'date' => '2009-12-15',
        ], [
            [
                'bill' => $bill,
                'amount' => 100,
            ],
        ]);
        $vendor = new AccountingVendor(self::$vendor->id);
        $apLedger->syncPayment($ledger, $payment);
        $documentId = $ledger->documents->getOrCreate(new Document(
            type: DocumentType::Payment,
            reference: (string) $payment->id,
            party: $vendor,
            date: CarbonImmutable::now(),
        ));

        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($invoiceDocumentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-10000, new Currency('USD')),
            ],
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Payment',
                'reference' => (string) $payment->id,
                'amount' => new Money(10000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($invoiceDocumentId, ApAccounts::AccountsPayable->value));

        // Edit the payment
        $editOp->edit($payment, ['amount' => 150]);
        $this->assertEquals(new Money(5000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(5000, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Payment',
                'reference' => (string) $payment->id,
                'amount' => new Money(5000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($invoiceDocumentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-10000, new Currency('USD')),
            ],
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Payment',
                'reference' => (string) $payment->id,
                'amount' => new Money(10000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($invoiceDocumentId, ApAccounts::AccountsPayable->value));

        // Void the payment
        $voidOp->void($payment);
        $this->assertEquals(new Money(-10000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
        ], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(-10000, new Currency('USD')), $ledger->reporting->getDocumentBalance($invoiceDocumentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-10000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($invoiceDocumentId, ApAccounts::AccountsPayable->value));

        // Void the invoice
        $bill->voided = true;
        $bill->saveOrFail();
        $apLedger->syncBill($ledger, $bill);
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($invoiceDocumentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([], $ledger->reporting->getDocumentTransactions($invoiceDocumentId, ApAccounts::AccountsPayable->value));
    }

    public function testSyncAdjustment(): void
    {
        $apLedger = $this->getLedger();
        $ledger = $apLedger->getLedger(self::$company);
        $createOp = self::getService('test.vendor_adjustment_create');
        $voidOp = self::getService('test.vendor_adjustment_void');

        // Create an invoice
        $bill = new Bill();
        $bill->vendor = self::$vendor;
        $bill->date = new CarbonImmutable('2009-12-15');
        $bill->number = 'INV-00002';
        $bill->currency = 'usd';
        $bill->total = 100;
        $bill->status = PayableDocumentStatus::Approved;
        $bill->saveOrFail();
        $vendor = new AccountingVendor(self::$vendor->id);
        $apLedger->syncBill($ledger, $bill);
        $invoiceDocumentId = $ledger->documents->getOrCreate(new Document(
            type: DocumentType::Invoice,
            reference: (string) $bill->id,
            party: $vendor,
            date: CarbonImmutable::now(),
        ));

        $this->assertEquals(new Money(-10000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(-10000, new Currency('USD')), $ledger->reporting->getDocumentBalance($invoiceDocumentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-10000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($invoiceDocumentId, ApAccounts::AccountsPayable->value));

        // Create an adjustment
        $adjustment = $createOp->create([
            'vendor' => self::$vendor,
            'currency' => 'usd',
            'amount' => 100,
            'bill' => $bill,
            'date' => '2009-12-15',
        ]);
        $vendor = new AccountingVendor(self::$vendor->id);
        $apLedger->syncAdjustment($ledger, $adjustment);
        $documentId = $ledger->documents->getOrCreate(new Document(
            type: DocumentType::Adjustment,
            reference: (string) $adjustment->id,
            party: $vendor,
            date: CarbonImmutable::now(),
        ));

        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($invoiceDocumentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-10000, new Currency('USD')),
            ],
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Adjustment',
                'reference' => (string) $adjustment->id,
                'amount' => new Money(10000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($invoiceDocumentId, ApAccounts::AccountsPayable->value));

        // Void the adjustment
        $voidOp->void($adjustment);
        $this->assertEquals(new Money(-10000, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(-10000, new Currency('USD')), $ledger->reporting->getDocumentBalance($invoiceDocumentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([
            [
                'transaction_date' => '2009-12-15',
                'document_type' => 'Invoice',
                'reference' => (string) $bill->id,
                'amount' => new Money(-10000, new Currency('USD')),
            ],
        ], $ledger->reporting->getDocumentTransactions($invoiceDocumentId, ApAccounts::AccountsPayable->value));

        // Void the invoice
        $bill->voided = true;
        $bill->saveOrFail();
        $apLedger->syncBill($ledger, $bill);
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getAccountingPartyBalance($vendor, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([], $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals(new Money(0, new Currency('USD')), $ledger->reporting->getDocumentBalance($invoiceDocumentId, ApAccounts::AccountsPayable->value));
        $this->assertEquals([], $ledger->reporting->getDocumentTransactions($invoiceDocumentId, ApAccounts::AccountsPayable->value));
    }

    private function populateLedger(Ledger $ledger): void
    {
        if ($this->populated) {
            return;
        }

        $vendor1 = new AccountingVendor(2);
        $vendor2 = new AccountingVendor(3);

        $invoice1 = new Document(
            type: DocumentType::Invoice,
            reference: '4',
            party: $vendor1,
            date: CarbonImmutable::now(),
            dueDate: null,
            number: 'INV-00003',
        );
        $ledger->syncDocument($invoice1, [
            new Transaction(
                date: $invoice1->date,
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Credit(100, 100),
                        party: $invoice1->party,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Debit(100, 100),
                        party: $invoice1->party,
                    ),
                ],
            ),
        ]);

        $invoice2 = new Document(
            type: DocumentType::Invoice,
            reference: '5',
            party: $vendor1,
            date: CarbonImmutable::now()->subDays(7),
            dueDate: null,
            number: 'INV-00004',
        );
        $ledger->syncDocument($invoice2, [
            new Transaction(
                date: $invoice2->date,
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Credit(200, 200),
                        party: $invoice2->party,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Debit(200, 200),
                        party: $invoice2->party,
                    ),
                ],
            ),
        ]);

        $invoice3 = new Document(
            type: DocumentType::Invoice,
            reference: '6',
            party: $vendor1,
            date: CarbonImmutable::now()->subDays(14),
            dueDate: CarbonImmutable::now()->addDays(7),
            number: 'INV-00004',
        );
        $ledger->syncDocument($invoice3, [
            new Transaction(
                date: $invoice3->date,
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Credit(300, 300),
                        party: $invoice3->party,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Debit(300, 300),
                        party: $invoice3->party,
                    ),
                ],
            ),
        ]);

        $invoice4 = new Document(
            type: DocumentType::Invoice,
            reference: '7',
            party: $vendor1,
            date: CarbonImmutable::now()->subDays(30),
            dueDate: CarbonImmutable::now()->subDays(7),
            number: 'INV-00005',
        );
        $ledger->syncDocument($invoice4, [
            new Transaction(
                date: $invoice4->date,
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Credit(400, 400),
                        party: $invoice4->party,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Debit(400, 400),
                        party: $invoice4->party,
                    ),
                ],
            ),
        ]);

        $invoice5 = new Document(
            type: DocumentType::Invoice,
            reference: '8',
            party: $vendor1,
            date: CarbonImmutable::now()->subDays(45),
            dueDate: CarbonImmutable::now()->subDays(45),
            number: 'INV-00006',
        );
        $ledger->syncDocument($invoice5, [
            new Transaction(
                date: $invoice5->date,
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Credit(500, 500),
                        party: $invoice5->party,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Debit(500, 500),
                        party: $invoice5->party,
                    ),
                ],
            ),
        ]);

        $invoice6 = new Document(
            type: DocumentType::Invoice,
            reference: '9',
            party: $vendor1,
            date: CarbonImmutable::now()->subDays(60),
            dueDate: CarbonImmutable::now()->subDays(60),
            number: 'INV-00007',
        );
        $ledger->syncDocument($invoice6, [
            new Transaction(
                date: $invoice6->date,
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Credit(600, 600),
                        party: $invoice6->party,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Debit(600, 600),
                        party: $invoice6->party,
                    ),
                ],
            ),
        ]);

        $creditNote = new Document(
            type: DocumentType::CreditNote,
            reference: '10',
            party: $vendor1,
            date: CarbonImmutable::now()->subDays(60),
            dueDate: null,
            number: 'CN-00002',
        );
        $ledger->syncDocument($creditNote, [
            new Transaction(
                date: $creditNote->date,
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Debit(300, 300),
                        party: $creditNote->party,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Credit(300, 300),
                        party: $creditNote->party,
                    ),
                ],
            ),
        ]);

        $invoice7 = new Document(
            type: DocumentType::Invoice,
            reference: '11',
            party: $vendor2,
            date: CarbonImmutable::now()->subDays(15),
            dueDate: null,
            number: 'INV-00008',
        );
        $ledger->syncDocument($invoice7, [
            new Transaction(
                date: $invoice7->date,
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Credit(10000, 10000),
                        party: $invoice7->party,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Debit(10000, 10000),
                        party: $invoice7->party,
                    ),
                ],
            ),
        ]);

        $voidedInvoice = new Document(
            type: DocumentType::Invoice,
            reference: '12',
            party: $vendor1,
            date: CarbonImmutable::now(),
            dueDate: null,
            number: 'INV-00009',
        );
        // sync then void
        $ledger->syncDocument($voidedInvoice, [
            new Transaction(
                date: $voidedInvoice->date,
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Credit(100000, 100000),
                        party: $voidedInvoice->party,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Debit(100000, 100000),
                        party: $voidedInvoice->party,
                    ),
                ],
            ),
        ]);
        $ledger->voidDocument($voidedInvoice);

        $this->populated = true;
    }

    public function testGetVendorBalances(): void
    {
        $apLedger = $this->getLedger();
        $ledger = $apLedger->getLedger(self::$company);
        $this->populateLedger($ledger);

        $expected = [
            [
                'party_id' => 2,
                'balance' => new Money(-1800, new Currency('USD')),
            ],
            [
                'party_id' => 3,
                'balance' => new Money(-10000, new Currency('USD')),
            ],
        ];
        $this->assertEquals($expected, $ledger->reporting->getPartyBalances(ApAccounts::AccountsPayable->value));
    }

    public function testAgingByDate(): void
    {
        $apLedger = $this->getLedger();
        $ledger = $apLedger->getLedger(self::$company);
        $agingBreakdown = new AgingBreakdown([0, 7, 14, 30, 60], 'date');
        $this->populateLedger($ledger);

        $expected = [
            [
                'age_lower' => 0,
                'amount' => new Money(-100, new Currency('usd')),
                'count' => 1,
            ],
            [
                'age_lower' => 7,
                'amount' => new Money(-200, new Currency('usd')),
                'count' => 1,
            ],
            [
                'age_lower' => 14,
                'amount' => new Money(-10300, new Currency('usd')),
                'count' => 2,
            ],
            [
                'age_lower' => 30,
                'amount' => new Money(-900, new Currency('usd')),
                'count' => 2,
            ],
            [
                'age_lower' => 60,
                'amount' => new Money(-300, new Currency('usd')),
                'count' => 2,
            ],
        ];

        $this->assertEquals($expected, $ledger->reporting->getAging($agingBreakdown, ApAccounts::AccountsPayable->value));
    }

    public function testAgingByDueDate(): void
    {
        $apLedger = $this->getLedger();
        $ledger = $apLedger->getLedger(self::$company);
        $agingBreakdown = new AgingBreakdown([-1, 0, 30, 60], 'due_date');
        $this->populateLedger($ledger);

        $expected = [
            [
                'age_lower' => -1,
                'amount' => new Money(-10300, new Currency('usd')),
                'count' => 5,
            ],
            [
                'age_lower' => 0,
                'amount' => new Money(-400, new Currency('usd')),
                'count' => 1,
            ],
            [
                'age_lower' => 30,
                'amount' => new Money(-500, new Currency('usd')),
                'count' => 1,
            ],
            [
                'age_lower' => 60,
                'amount' => new Money(-600, new Currency('usd')),
                'count' => 1,
            ],
        ];

        $this->assertEquals($expected, $ledger->reporting->getAging($agingBreakdown, ApAccounts::AccountsPayable->value));
    }
}
