<?php

namespace App\Tests\Core\Ledger;

use App\Core\Ledger\Enums\AccountType;
use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Ledger\Ledger;
use App\Core\Ledger\Repository\LedgerRepository;
use App\Core\Ledger\ValueObjects\AccountingCustomer;
use App\Core\Ledger\ValueObjects\Debit;
use App\Core\Ledger\ValueObjects\Document;
use App\Core\Ledger\ValueObjects\Credit;
use App\Core\Ledger\ValueObjects\LedgerEntry;
use App\Core\Ledger\ValueObjects\Transaction;
use App\PaymentProcessing\Enums\MerchantAccountLedgerAccounts;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Exchanger\Exchanger;
use Exchanger\Service\PhpArray;
use Money\Currency;
use Money\Money;

class LedgerTest extends AppTestCase
{
    private static array $ledgerNames = [];

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        foreach (self::$ledgerNames as $name) {
            self::getService('test.database')->executeQuery('SET foreign_key_checks = 0; DELETE FROM Ledgers WHERE name="'.$name.'"; SET foreign_key_checks = 1;');
        }
    }

    private function getLedgerRepository(): LedgerRepository
    {
        return new LedgerRepository(self::getService('test.database'));
    }

    private function getLedger(): Ledger
    {
        $name = uniqid();
        self::$ledgerNames[] = $name;

        return $this->getLedgerRepository()->findOrCreate($name, 'USD');
    }

    public function testBlankLedger(): void
    {
        $ledger = $this->getLedger();
        $this->assertGreaterThan(0, $ledger->id);
        $this->assertEquals('USD', $ledger->baseCurrency);
        $this->assertEquals([], $ledger->reporting->getAccountBalances());
    }

    public function testInvoicingScenario(): void
    {
        $ledger = $this->getLedger();
        $ledger->chartOfAccounts->findOrCreate('Accounts Receivable', AccountType::Asset, 'USD');
        $ledger->chartOfAccounts->findOrCreate('Sales', AccountType::Revenue, 'USD');
        $ledger->chartOfAccounts->findOrCreate('Bank Account', AccountType::Asset, 'USD');

        // Create an invoice
        $customer = new AccountingCustomer(1234);
        $invoice = new Document(
            type: DocumentType::Invoice,
            reference: 'INV-00001',
            party: new AccountingCustomer(1234),
            date: CarbonImmutable::now(),
        );
        $documentId = $ledger->documents->create($invoice);
        $ledger->createTransaction($documentId, new Transaction(
            date: CarbonImmutable::now(),
            currency: 'USD',
            entries: [
                new LedgerEntry(
                    account: 'Accounts Receivable',
                    amount: new Debit(10000),
                    party: $customer,
                ),
                new LedgerEntry(
                    account: 'Sales',
                    amount: new Credit(10000),
                    party: $customer,
                ),
            ],
        ));

        // Check the account balances
        $this->assertEquals([
            [
                'name' => 'Accounts Receivable',
                'balance' => new Money(10000, new Currency('USD')),
            ],
            [
                'name' => 'Bank Account',
                'balance' => new Money(0, new Currency('USD')),
            ],
            [
                'name' => 'Sales',
                'balance' => new Money(-10000, new Currency('USD')),
            ],
        ], $ledger->reporting->getAccountBalances());

        // Pay the invoice
        $payment = new Document(
            type: DocumentType::Payment,
            reference: 'PMT-00001',
            party: new AccountingCustomer(1234),
            date: CarbonImmutable::now(),
        );
        $documentId = $ledger->documents->create($payment);
        $ledger->createTransaction($documentId, new Transaction(
            date: CarbonImmutable::now(),
            currency: 'USD',
            entries: [
                new LedgerEntry(
                    account: 'Accounts Receivable',
                    amount: new Credit(10000),
                    party: $customer,
                ),
                new LedgerEntry(
                    account: 'Bank Account',
                    amount: new Debit(10000),
                    party: $customer,
                ),
            ],
        ));

        // Check the account balances
        $this->assertEquals([
            [
                'name' => 'Accounts Receivable',
                'balance' => new Money(0, new Currency('USD')),
            ],
            [
                'name' => 'Bank Account',
                'balance' => new Money(10000, new Currency('USD')),
            ],
            [
                'name' => 'Sales',
                'balance' => new Money(-10000, new Currency('USD')),
            ],
        ], $ledger->reporting->getAccountBalances());

        // Void the invoice
        $ledger->voidDocument($invoice);

        // Check the account balances
        $this->assertEquals([
            [
                'name' => 'Accounts Receivable',
                'balance' => new Money(-10000, new Currency('USD')),
            ],
            [
                'name' => 'Bank Account',
                'balance' => new Money(10000, new Currency('USD')),
            ],
            [
                'name' => 'Sales',
                'balance' => new Money(0, new Currency('USD')),
            ],
        ], $ledger->reporting->getAccountBalances());
    }

    /**
     * @depends testSyncDocument
     */
    public function testBlockUnbalancedTransaction(): void
    {
        $ledger = $this->getLedger();
        $ledger->chartOfAccounts->findOrCreate(MerchantAccountLedgerAccounts::Payments->value, AccountType::Revenue, 'USD');
        $ledger->chartOfAccounts->findOrCreate(MerchantAccountLedgerAccounts::Refunds->value, AccountType::Revenue, 'USD');


        new AccountingCustomer(1234);
        $document = new Document(
            type: DocumentType::Invoice,
            reference: 'INV-00001',
            party: new AccountingCustomer(1234),
            date: CarbonImmutable::now(),
        );

        try {
            $ledger->createTransaction(1234, new Transaction(
                date: CarbonImmutable::now(),
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::Payments->value,
                        amount: new Debit(100),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::Refunds->value,
                        amount: new Credit(99)
                    ),
                ],
            ));
            $this->fail('Expected exception not thrown');
        } catch (LedgerException $e) {
            $this->assertEquals('Unbalanced journal entry in transaction currency: 1', $e->getMessage());
        }

        $transaction = new Transaction(
            date: CarbonImmutable::now(),
            currency: 'USD',
            entries: [
                new LedgerEntry(
                    account: MerchantAccountLedgerAccounts::Refunds->value,
                    amount: new Debit(100, 100),
                ),
                new LedgerEntry(
                    account: MerchantAccountLedgerAccounts::Payments->value,
                    amount: new Credit(99, 100)
                ),
            ],
        );


        try {
            $ledger->syncDocument($document, [$transaction]);
            $this->fail('Expected exception not thrown');
        } catch (LedgerException $e) {
            $this->assertEquals('Unbalanced journal entry: 1', $e->getMessage());
        }


        $ledger->chartOfAccounts->findOrCreate(MerchantAccountLedgerAccounts::RoundingDifference->value, AccountType::Revenue, 'USD');
        $document = new Document(
            type: DocumentType::Invoice,
            reference: 'INV-00002',
            party: new AccountingCustomer(1234),
            date: CarbonImmutable::now(),
        );
        $ledger->syncDocument($document, [$transaction]);

        $this->assertEquals([
            [
                'name' => MerchantAccountLedgerAccounts::Payments->value,
                'balance' => new Money(-99, new Currency('USD')),
            ],
            [
                'name' => MerchantAccountLedgerAccounts::Refunds->value,
                'balance' => new Money(100, new Currency('USD')),
            ],
            [
                'name' => MerchantAccountLedgerAccounts::RoundingDifference->value,
                'balance' => new Money(-1, new Currency('USD')),
            ],
        ], $ledger->reporting->getAccountBalances());
    }

    public function testSyncDocument(): void
    {
        $ledger = $this->getLedger();
        $ledger->chartOfAccounts->findOrCreate('Accounts Receivable', AccountType::Asset, 'USD');
        $ledger->chartOfAccounts->findOrCreate('Sales', AccountType::Revenue, 'USD');
        $ledger->chartOfAccounts->findOrCreate('Bank Account', AccountType::Asset, 'USD');

        // Create an invoice
        $customer = new AccountingCustomer(1234);
        $invoice = new Document(
            type: DocumentType::Invoice,
            reference: 'INV-00002',
            party: new AccountingCustomer(1234),
            date: CarbonImmutable::now(),
        );
        $transactions = [
            new Transaction(
                date: CarbonImmutable::now(),
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: 'Accounts Receivable',
                        amount: new Debit(50000),
                        party: $customer,
                    ),
                    new LedgerEntry(
                        account: 'Sales',
                        amount: new Credit(50000),
                        party: $customer,
                    ),
                ],
            ),
        ];
        $ledger->syncDocument($invoice, $transactions);

        // Check the account balances
        $this->assertEquals([
            [
                'name' => 'Accounts Receivable',
                'balance' => new Money(50000, new Currency('USD')),
            ],
            [
                'name' => 'Bank Account',
                'balance' => new Money(0, new Currency('USD')),
            ],
            [
                'name' => 'Sales',
                'balance' => new Money(-50000, new Currency('USD')),
            ],
        ], $ledger->reporting->getAccountBalances());

        // Modify details about the invoice
        $invoice = new Document(
            type: DocumentType::Invoice,
            reference: 'INV-00002',
            party: new AccountingCustomer(1234),
            date: new CarbonImmutable('2022-01-01'),
        );
        $transactions = [
            new Transaction(
                date: new CarbonImmutable('2022-01-01'),
                currency: 'USD',
                entries: [
                    new LedgerEntry(
                        account: 'Accounts Receivable',
                        amount: new Debit(30000),
                        party: $customer,
                    ),
                    new LedgerEntry(
                        account: 'Sales',
                        amount: new Credit(30000),
                        party: $customer,
                    ),
                ],
            ),
        ];
        $ledger->syncDocument($invoice, $transactions);

        // Check the account balances
        $this->assertEquals([
            [
                'name' => 'Accounts Receivable',
                'balance' => new Money(30000, new Currency('USD')),
            ],
            [
                'name' => 'Bank Account',
                'balance' => new Money(0, new Currency('USD')),
            ],
            [
                'name' => 'Sales',
                'balance' => new Money(-30000, new Currency('USD')),
            ],
        ], $ledger->reporting->getAccountBalances());
    }

    public function testConvertCurrency(): void
    {
        $ledger = $this->getLedger();
        $exchanger = new Exchanger(new PhpArray([], ['2022-07-12' => ['EUR/USD' => 1.5]])); /* @phpstan-ignore-line */

        // Test identity
        $this->assertEquals(10000, $ledger->convertCurrency($exchanger, 'USD', new CarbonImmutable('2022-07-12'), 10000));
        // Convert 100 USD to 100 EUR
        $this->assertEquals(15000, $ledger->convertCurrency($exchanger, 'EUR', new CarbonImmutable('2022-07-12'), 10000));
    }

    public function testGetAccountBalance(): void
    {
        $ledger = $this->getLedger();
        $ledger->chartOfAccounts->findOrCreate('Accounts Receivable', AccountType::Asset, 'USD');
        $ledger->chartOfAccounts->findOrCreate('Sales', AccountType::Revenue, 'USD');
        $reporting = $ledger->reporting;

        $this->assertEquals(new Money(0, new Currency('USD')), $reporting->getAccountBalance('Accounts Receivable'));

        // insert a transaction
        $customer = new AccountingCustomer(1234);
        $invoice = new Document(
            type: DocumentType::Invoice,
            reference: 'INV-00001',
            party: new AccountingCustomer(1234),
            date: new CarbonImmutable('2022-07-14'),
        );
        $documentId = $ledger->documents->create($invoice);
        $ledger->createTransaction($documentId, new Transaction(
            date: new CarbonImmutable('2022-07-14'),
            currency: 'USD',
            entries: [
                new LedgerEntry(
                    account: 'Accounts Receivable',
                    amount: new Debit(10000),
                    party: $customer,
                ),
                new LedgerEntry(
                    account: 'Sales',
                    amount: new Credit(10000),
                    party: $customer,
                ),
            ],
        ));

        $this->assertEquals(new Money(0, new Currency('USD')), $reporting->getAccountBalance('Accounts Receivable', new CarbonImmutable('2022-07-13')));
        $this->assertEquals(new Money(10000, new Currency('USD')), $reporting->getAccountBalance('Accounts Receivable', new CarbonImmutable('2022-07-14')));
    }
}
