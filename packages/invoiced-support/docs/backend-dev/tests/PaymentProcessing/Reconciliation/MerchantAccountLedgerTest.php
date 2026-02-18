<?php

namespace App\Tests\PaymentProcessing\Reconciliation;

use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\ValueObjects\AccountingVendor;
use App\Core\Ledger\ValueObjects\Credit;
use App\Core\Ledger\ValueObjects\Debit;
use App\Core\Ledger\ValueObjects\Document;
use App\Core\Ledger\ValueObjects\LedgerEntry;
use App\Core\Ledger\ValueObjects\Transaction;
use App\PaymentProcessing\Enums\MerchantAccountLedgerAccounts;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class MerchantAccountLedgerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasMerchantAccount(AdyenGateway::ID);
    }

    private function getLedger(): MerchantAccountLedger
    {
        return self::getService('test.merchant_account_ledger');
    }

    /**
     * @dataProvider provideDocuments
     */
    public function testMakeDocument(MerchantAccountTransactionType $transactionType, DocumentType $documentType): void
    {
        $ledger = $this->getLedger();
        $transaction = new MerchantAccountTransaction();
        $transaction->available_on = new CarbonImmutable('2024-01-01');
        $transaction->type = $transactionType;
        $document = $ledger->makeDocument($transaction);
        $this->assertEquals(new Document(
            type: $documentType,
            reference: (string) $transaction->id,
            party: new AccountingVendor(1),
            date: new CarbonImmutable($transaction->available_on),
            number: $transaction->reference,
        ), $document);
    }

    public function provideDocuments(): array
    {
        return [
            [
                MerchantAccountTransactionType::Payment,
                DocumentType::Payment,
            ],
            [
                MerchantAccountTransactionType::Payout,
                DocumentType::Payout,
            ],
            [
                MerchantAccountTransactionType::PayoutReversal,
                DocumentType::PayoutReversal,
            ],
            [
                MerchantAccountTransactionType::Fee,
                DocumentType::Fee,
            ],
            [
                MerchantAccountTransactionType::Refund,
                DocumentType::Refund,
            ],
            [
                MerchantAccountTransactionType::RefundReversal,
                DocumentType::Refund,
            ],
            [
                MerchantAccountTransactionType::Dispute,
                DocumentType::Chargeback,
            ],
            [
                MerchantAccountTransactionType::DisputeReversal,
                DocumentType::ChargebackReversal,
            ],
            [
                MerchantAccountTransactionType::Adjustment,
                DocumentType::Adjustment,
            ],
        ];
    }

    public function testMakeTransaction(): void
    {
        $ledger = $this->getLedger();
        $transaction = new MerchantAccountTransaction();
        $transaction->available_on = new CarbonImmutable('2024-01-01');
        $transaction->currency = 'usd';
        $transaction->description = 'Test';
        $ledgerTransaction = $ledger->makeTransaction($transaction, []);
        $this->assertEquals(new Transaction(
            date: new CarbonImmutable('2024-01-01'),
            currency: 'USD',
            entries: [],
            description: 'Test',
        ), $ledgerTransaction);
    }

    /**
     * @dataProvider provideLedgerEntries
     */
    public function testMakeLedgerEntries(MerchantAccountTransaction $transaction, array $expectedLedgerEntries): void
    {
        $ledger = $this->getLedger();
        $subLedger = $ledger->getLedger(self::$merchantAccount);
        $this->assertEquals($expectedLedgerEntries, $ledger->makeLedgerEntries($subLedger, $transaction));
    }

    public function provideLedgerEntries(): array
    {
        return [
            // Payment
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::Payment,
                    'currency' => 'usd',
                    'amount' => 100,
                    'fee' => 2.90,
                    'net' => 97.1,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::Payments->value,
                        amount: new Credit(10000, 10000),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                        amount: new Debit(9710, 9710),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::ProcessingFees->value,
                        amount: new Debit(290, 290),
                        party: new AccountingVendor(1),
                    ),
                ],
            ],
            // Payment (net zero)
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::Payment,
                    'currency' => 'usd',
                    'amount' => 2,
                    'fee' => 2,
                    'net' => 0,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::Payments->value,
                        amount: new Credit(200, 200),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::ProcessingFees->value,
                        amount: new Debit(200, 200),
                        party: new AccountingVendor(1),
                    ),
                ],
            ],
            // Refund
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::Refund,
                    'currency' => 'usd',
                    'amount' => -100,
                    'fee' => 0,
                    'net' => -100,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::Refunds->value,
                        amount: new Debit(10000, 10000),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                        amount: new Credit(10000, 10000),
                    ),
                ],
            ],
            // Refund Reversal
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::RefundReversal,
                    'currency' => 'usd',
                    'amount' => 100,
                    'fee' => 0,
                    'net' => 100,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                        amount: new Debit(10000, 10000),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::Refunds->value,
                        amount: new Credit(10000, 10000),
                    ),
                ],
            ],
            // Payout
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::Payout,
                    'currency' => 'usd',
                    'amount' => -100,
                    'fee' => 0,
                    'net' => -100,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                        amount: new Credit(10000, 10000),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::BankAccount->value,
                        amount: new Debit(10000, 10000),
                    ),
                ],
            ],
            // Payout Reversal
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::PayoutReversal,
                    'currency' => 'usd',
                    'amount' => 100,
                    'fee' => 0,
                    'net' => 100,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                        amount: new Debit(10000, 10000),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::BankAccount->value,
                        amount: new Credit(10000, 10000),
                    ),
                ],
            ],
            // Dispute
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::Dispute,
                    'currency' => 'usd',
                    'amount' => -100,
                    'fee' => 15,
                    'net' => -115,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                        amount: new Credit(11500, 11500),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::Disputes->value,
                        amount: new Debit(10000, 10000),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::ProcessingFees->value,
                        amount: new Debit(1500, 1500),
                        party: new AccountingVendor(1),
                    ),
                ],
            ],
            // Dispute Reversal
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::DisputeReversal,
                    'currency' => 'usd',
                    'amount' => 100,
                    'fee' => 0,
                    'net' => 100,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                        amount: new Debit(10000, 10000),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::Disputes->value,
                        amount: new Credit(10000, 10000),
                    ),
                ],
            ],
            // Fee
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::Fee,
                    'currency' => 'usd',
                    'amount' => -10.37,
                    'fee' => 0,
                    'net' => -10.37,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                        amount: new Credit(1037, 1037),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::ProcessingFees->value,
                        amount: new Debit(1037, 1037),
                        party: new AccountingVendor(1),
                    ),
                ],
            ],
            // Positive Adjustment
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::Adjustment,
                    'currency' => 'usd',
                    'amount' => 100,
                    'fee' => 0,
                    'net' => 100,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::Payments->value,
                        amount: new Credit(10000, 10000),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                        amount: new Debit(10000, 10000),
                    ),
                ],
            ],
            // Negative Adjustment
            [
                new MerchantAccountTransaction([
                    'type' => MerchantAccountTransactionType::Adjustment,
                    'currency' => 'usd',
                    'amount' => -100,
                    'fee' => 0,
                    'net' => -100,
                ]),
                [
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::Payments->value,
                        amount: new Debit(10000, 10000),
                    ),
                    new LedgerEntry(
                        account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                        amount: new Credit(10000, 10000),
                    ),
                ],
            ],
        ];
    }
}
