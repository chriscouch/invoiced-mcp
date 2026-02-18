<?php

namespace App\PaymentProcessing\Reconciliation;

use App\Core\I18n\CurrencyExchangerFactory;
use App\Core\Ledger\Enums\AccountType;
use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Ledger\Ledger;
use App\Core\Ledger\Repository\LedgerRepository;
use App\Core\Ledger\ValueObjects\AccountingVendor;
use App\Core\Ledger\ValueObjects\Credit;
use App\Core\Ledger\ValueObjects\Debit;
use App\Core\Ledger\ValueObjects\Document;
use App\Core\Ledger\ValueObjects\LedgerEntry;
use App\Core\Ledger\ValueObjects\Transaction;
use App\PaymentProcessing\Enums\MerchantAccountLedgerAccounts;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use Carbon\CarbonImmutable;
use Exchanger\Exchanger;

class MerchantAccountLedger
{
    private Exchanger $exchanger;
    /** @var Ledger[] */
    private array $ledgers = [];

    public function __construct(
        private LedgerRepository $ledgerRepository,
        private CurrencyExchangerFactory $exchangerFactory,
    ) {
        $this->exchanger = $this->exchangerFactory->make();
    }

    public function getLedger(MerchantAccount $merchantAccount): Ledger
    {
        if (isset($this->ledgers[$merchantAccount->id])) {
            return $this->ledgers[$merchantAccount->id];
        }

        $name = 'Merchant Account - '.$merchantAccount->id;
        if ($ledger = $this->ledgerRepository->find($name)) {
            return $ledger;
        }

        $ledger = $this->ledgerRepository->create($name, $merchantAccount->tenant()->currency);
        $this->setUpChartOfAccounts($ledger);
        $this->ledgers[$merchantAccount->id] = $ledger;

        return $ledger;
    }

    private function setUpChartOfAccounts(Ledger $ledger): void
    {
        $ledger->chartOfAccounts->findOrCreate(MerchantAccountLedgerAccounts::Payments->value, AccountType::Revenue, $ledger->baseCurrency);
        $ledger->chartOfAccounts->findOrCreate(MerchantAccountLedgerAccounts::Refunds->value, AccountType::Revenue, $ledger->baseCurrency);
        $ledger->chartOfAccounts->findOrCreate(MerchantAccountLedgerAccounts::Disputes->value, AccountType::Revenue, $ledger->baseCurrency);
        $ledger->chartOfAccounts->findOrCreate(MerchantAccountLedgerAccounts::MerchantAccount->value, AccountType::Asset, $ledger->baseCurrency);
        $ledger->chartOfAccounts->findOrCreate(MerchantAccountLedgerAccounts::BankAccount->value, AccountType::Asset, $ledger->baseCurrency);
        $ledger->chartOfAccounts->findOrCreate(MerchantAccountLedgerAccounts::ProcessingFees->value, AccountType::Expense, $ledger->baseCurrency);
        $ledger->chartOfAccounts->findOrCreate(MerchantAccountLedgerAccounts::RoundingDifference->value, AccountType::Revenue, $ledger->baseCurrency);
    }

    /**
     * @throws LedgerException
     */
    public function syncTransaction(Ledger $ledger, MerchantAccountTransaction $transaction): void
    {
        $document = $this->makeDocument($transaction);
        $ledgerEntries = $this->makeLedgerEntries($ledger, $transaction);
        $transaction = $this->makeTransaction($transaction, $ledgerEntries);
        $ledger->syncDocument($document, [$transaction]);
    }

    public function makeDocument(MerchantAccountTransaction $transaction): Document
    {
        $documentType = match ($transaction->type) {
            MerchantAccountTransactionType::Payment => DocumentType::Payment,
            MerchantAccountTransactionType::Payout => DocumentType::Payout,
            MerchantAccountTransactionType::PayoutReversal => DocumentType::PayoutReversal,
            MerchantAccountTransactionType::Fee => DocumentType::Fee,
            MerchantAccountTransactionType::Refund => DocumentType::Refund,
            MerchantAccountTransactionType::RefundReversal => DocumentType::Refund,
            MerchantAccountTransactionType::Dispute => DocumentType::Chargeback,
            MerchantAccountTransactionType::DisputeReversal => DocumentType::ChargebackReversal,
            MerchantAccountTransactionType::Adjustment, MerchantAccountTransactionType::TopUp => DocumentType::Adjustment,
        };

        return new Document(
            type: $documentType,
            reference: (string) $transaction->id,
            party: new AccountingVendor(1),
            date: new CarbonImmutable($transaction->available_on),
            number: $transaction->reference,
        );
    }

    /**
     * @param LedgerEntry[] $ledgerEntries
     */
    public function makeTransaction(MerchantAccountTransaction $transaction, array $ledgerEntries): Transaction
    {
        return new Transaction(
            date: new CarbonImmutable($transaction->available_on),
            currency: strtoupper($transaction->currency),
            entries: $ledgerEntries,
            description: $transaction->description,
        );
    }

    /**
     * @return LedgerEntry[]
     */
    public function makeLedgerEntries(Ledger $ledger, MerchantAccountTransaction $transaction): array
    {
        $gross = $transaction->getAmount()->amount;
        $fee = $transaction->getFee()->amount;
        $net = $transaction->getNet()->amount;

        // Convert the transaction currency amounts into the ledger currency
        $date = new CarbonImmutable($transaction->available_on);
        //we can't get currency converter for the future
        if ($date->isFuture()) {
            $date = CarbonImmutable::now();
        }
        $grossConverted = $ledger->convertCurrency($this->exchanger, $transaction->currency, $date, $gross);
        $feeConverted = $ledger->convertCurrency($this->exchanger, $transaction->currency, $date, $fee);
        $netConverted = $ledger->convertCurrency($this->exchanger, $transaction->currency, $date, $net);

        $ledgerEntries = match ($transaction->type) {
            MerchantAccountTransactionType::Adjustment, MerchantAccountTransactionType::TopUp => $this->makeEntriesAdjustment($grossConverted, $gross, $netConverted, $net),
            MerchantAccountTransactionType::Dispute => $this->makeEntriesDispute($grossConverted, $gross, $netConverted, $net),
            MerchantAccountTransactionType::DisputeReversal => $this->makeEntriesDisputeReversal($grossConverted, $gross, $netConverted, $net),
            MerchantAccountTransactionType::Fee => $this->makeEntriesFee($grossConverted, $gross),
            MerchantAccountTransactionType::Payment => $this->makeEntriesPayment($grossConverted, $gross, $netConverted, $net),
            MerchantAccountTransactionType::Payout => $this->makeEntriesPayout($grossConverted, $gross, $netConverted, $net),
            MerchantAccountTransactionType::PayoutReversal => $this->makeEntriesPayoutReversal($grossConverted, $gross, $netConverted, $net),
            MerchantAccountTransactionType::Refund => $this->makeEntriesRefund($grossConverted, $gross, $netConverted, $net),
            MerchantAccountTransactionType::RefundReversal => $this->makeEntriesRefundReversal($grossConverted, $gross, $netConverted, $net),
        };

        // The fee is a positive amount
        if ($fee) {
            $ledgerEntries[] = new LedgerEntry(
                account: MerchantAccountLedgerAccounts::ProcessingFees->value,
                amount: $fee > 0 ? new Debit($feeConverted, $fee) : new Credit(-$feeConverted, -$fee),
                party: new AccountingVendor(1),
            );
        }

        // Remove zero entries because these are not permitted in the ledger
        return array_values(
            array_filter($ledgerEntries, fn ($entry) => 0 != $entry->amount->amount)
        );
    }

    /**
     * @return LedgerEntry[]
     */
    private function makeEntriesPayment(int $grossConverted, int $gross, int $netConverted, int $net): array
    {
        // Payments are a positive amount
        return [
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::Payments->value,
                amount: $gross > 0 ? new Credit($grossConverted, $gross) : new Debit(-$grossConverted, -$gross),
            ),
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                amount: $net > 0 ? new Debit($netConverted, $net) : new Credit(-$netConverted, -$net),
            ),
        ];
    }

    /**
     * @return LedgerEntry[]
     */
    private function makeEntriesRefund(int $grossConverted, int $gross, int $netConverted, int $net): array
    {
        // Refunds are a negative amount
        return [
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::Refunds->value,
                amount: $gross > 0 ? new Credit($grossConverted, $gross) : new Debit(-$grossConverted, -$gross),
            ),
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                amount: $net > 0 ? new Debit($netConverted, $net) : new Credit(-$netConverted, -$net),
            ),
        ];
    }

    /**
     * @return LedgerEntry[]
     */
    private function makeEntriesPayout(int $grossConverted, int $gross, int $netConverted, int $net): array
    {
        // Payouts are a negative amount
        return [
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                amount: $net > 0 ? new Debit($netConverted, $net) : new Credit(-$netConverted, -$net),
            ),
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::BankAccount->value,
                amount: $gross > 0 ? new Credit($grossConverted, $gross) : new Debit(-$grossConverted, -$gross),
            ),
        ];
    }

    /**
     * @return LedgerEntry[]
     */
    private function makeEntriesPayoutReversal(int $grossConverted, int $gross, int $netConverted, int $net): array
    {
        // Payout reversals are a positive amount
        return [
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                amount: $net > 0 ? new Debit($netConverted, $net) : new Credit(-$netConverted, -$net),
            ),
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::BankAccount->value,
                amount: $gross > 0 ? new Credit($grossConverted, $gross) : new Debit(-$grossConverted, -$gross),
            ),
        ];
    }

    /**
     * @return LedgerEntry[]
     */
    private function makeEntriesDispute(int $grossConverted, int $gross, int $netConverted, int $net): array
    {
        // Disputes are a negative amount
        return [
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                amount: $net > 0 ? new Debit($netConverted, $net) : new Credit(-$netConverted, -$net),
            ),
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::Disputes->value,
                amount: $gross > 0 ? new Credit($grossConverted, $gross) : new Debit(-$grossConverted, -$gross),
            ),
        ];
    }

    /**
     * @return LedgerEntry[]
     */
    private function makeEntriesDisputeReversal(int $grossConverted, int $gross, int $netConverted, int $net): array
    {
        // Dispute reversals are a positive amount
        return [
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                amount: $net > 0 ? new Debit($netConverted, $net) : new Credit(-$netConverted, -$net),
            ),
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::Disputes->value,
                amount: $gross > 0 ? new Credit($grossConverted, $gross) : new Debit(-$grossConverted, -$gross),
            ),
        ];
    }

    /**
     * @return LedgerEntry[]
     */
    private function makeEntriesFee(int $grossConverted, int $gross): array
    {
        // Fees are a negative amount
        return [
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                amount: $gross > 0 ? new Debit($grossConverted, $gross) : new Credit(-$grossConverted, -$gross),
            ),
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::ProcessingFees->value,
                amount: $grossConverted > 0 ? new Credit($grossConverted, $gross) : new Debit(-$grossConverted, -$gross),
                party: new AccountingVendor(1),
            ),
        ];
    }

    /**
     * @return LedgerEntry[]
     */
    private function makeEntriesAdjustment(int $grossConverted, int $gross, int $netConverted, int $net): array
    {
        // Adjustments can be a positive or negative amount
        return [
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::Payments->value,
                amount: $gross > 0 ? new Credit($grossConverted, $gross) : new Debit(-$grossConverted, -$gross),
            ),
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                amount: $net > 0 ? new Debit($netConverted, $net) : new Credit(-$netConverted, -$net),
            ),
        ];
    }

    private function makeEntriesRefundReversal(int $grossConverted, int $gross, int $netConverted, int $net): array
    {
        return [
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::MerchantAccount->value,
                amount: $gross > 0 ? new Debit($grossConverted, $gross) : new Credit(-$grossConverted, -$gross),
            ),
            new LedgerEntry(
                account: MerchantAccountLedgerAccounts::Refunds->value,
                amount: $net > 0 ? new Credit($netConverted, $net) : new Debit(-$netConverted, -$net),
            ),
        ];
    }
}
