<?php

namespace App\AccountsPayable\Ledger;

use App\AccountsPayable\Enums\ApAccounts;
use App\AccountsPayable\Enums\VendorPaymentItemTypes;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\VendorAdjustment;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Models\VendorPayment;
use App\Companies\Models\Company;
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
use Carbon\CarbonImmutable;
use Exchanger\Exchanger;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Parser\DecimalMoneyParser;

class AccountsPayableLedger
{
    private Exchanger $exchanger;

    public function __construct(
        private LedgerRepository $ledgerRepository,
        private CurrencyExchangerFactory $exchangerFactory,
    ) {
        $this->exchanger = $this->exchangerFactory->make();
    }

    public function getLedger(Company $company): Ledger
    {
        $name = 'Accounts Payable - '.$company->id;
        if ($ledger = $this->ledgerRepository->find($name)) {
            return $ledger;
        }

        $ledger = $this->ledgerRepository->create($name, $company->currency);
        $this->setUpChartOfAccounts($ledger);

        return $ledger;
    }

    private function setUpChartOfAccounts(Ledger $ledger): void
    {
        $ledger->chartOfAccounts->findOrCreate(ApAccounts::AccountsPayable->value, AccountType::Liability, $ledger->baseCurrency);
        $ledger->chartOfAccounts->findOrCreate(ApAccounts::Purchases->value, AccountType::Expense, $ledger->baseCurrency);
        $ledger->chartOfAccounts->findOrCreate(ApAccounts::Cash->value, AccountType::Asset, $ledger->baseCurrency);
        $ledger->chartOfAccounts->findOrCreate(ApAccounts::ConvenienceFee->value, AccountType::Expense, $ledger->baseCurrency);
    }

    /**
     * @throws LedgerException
     */
    public function syncBill(Ledger $ledger, Bill $bill): void
    {
        $vendor = new AccountingVendor($bill->vendor_id);

        $exchanger = $this->exchangerFactory->make();
        $currencies = new ISOCurrencies();
        $moneyParser = new DecimalMoneyParser($currencies);
        $date = new CarbonImmutable($bill->date);
        $total = (int) $moneyParser->parse((string) $bill->total, new Currency($bill->currency))->getAmount(); /* @phpstan-ignore-line */
        $totalConverted = $ledger->convertCurrency($exchanger, $bill->currency, $date, $total);

        $transactions = [];
        $document = new Document(
            type: DocumentType::Invoice,
            reference: (string) $bill->id,
            party: $vendor,
            date: $date,
            dueDate: $bill->due_date ? new CarbonImmutable($bill->due_date) : null,
            number: $bill->number,
        );

        if ($bill->voided) {
            $ledger->voidDocument($document);
        } else {
            $transactions[] = new Transaction(
                date: $date,
                currency: $bill->currency,
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Credit($totalConverted, $total),
                        party: $vendor,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Debit($totalConverted, $total),
                        party: $vendor,
                    ),
                ],
            );

            $ledger->syncDocument($document, $transactions);
        }
    }

    /**
     * @throws LedgerException
     */
    public function syncVendorCredit(Ledger $ledger, VendorCredit $vendorCredit): void
    {
        $vendor = new AccountingVendor($vendorCredit->vendor_id);

        $exchanger = $this->exchanger;
        $currencies = new ISOCurrencies();
        $moneyParser = new DecimalMoneyParser($currencies);
        $date = new CarbonImmutable($vendorCredit->date);
        $total = (int) $moneyParser->parse((string) $vendorCredit->total, new Currency($vendorCredit->currency))->getAmount(); /* @phpstan-ignore-line */
        $totalConverted = $ledger->convertCurrency($exchanger, $vendorCredit->currency, $date, $total);

        $transactions = [];
        $document = new Document(
            type: DocumentType::CreditNote,
            reference: (string) $vendorCredit->id,
            party: $vendor,
            date: $date,
            number: $vendorCredit->number,
        );

        if ($vendorCredit->voided) {
            $ledger->voidDocument($document);
        } else {
            $transactions[] = new Transaction(
                date: $date,
                currency: $vendorCredit->currency,
                entries: [
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Debit($totalConverted, $total),
                        party: $vendor,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::Purchases->value,
                        amount: new Credit($totalConverted, $total),
                        party: $vendor,
                    ),
                ],
            );

            $ledger->syncDocument($document, $transactions);
        }
    }

    /**
     * @throws LedgerException
     */
    public function syncPayment(Ledger $ledger, VendorPayment $payment): void
    {
        $vendor = new AccountingVendor($payment->vendor_id);
        $date = new CarbonImmutable($payment->date);
        $document = new Document(
            type: DocumentType::Payment,
            reference: (string) $payment->id,
            party: $vendor,
            date: $date,
            number: $payment->reference,
        );

        if ($payment->voided) {
            $ledger->voidDocument($document);

            return;
        }

        $transactions = [];
        $exchanger = $this->exchanger;
        $currencies = new ISOCurrencies();
        $moneyParser = new DecimalMoneyParser($currencies);
        $currency = new Currency($payment->currency); /* @phpstan-ignore-line */
        $total = (int) $moneyParser->parse((string) $payment->amount, $currency)->getAmount();
        $totalConverted = $ledger->convertCurrency($exchanger, $payment->currency, $date, $total);
        $remaining = $moneyParser->parse((string) $payment->amount, $currency);
        $entries = [
            new LedgerEntry(
                account: ApAccounts::Cash->value,
                amount: new Credit($totalConverted, $total),
                party: $vendor,
            ),
        ];

        foreach ($payment->getItems() as $paymentItem) {
            $lineAmount = $moneyParser->parse((string) $paymentItem->amount, $currency);
            $lineTotal = (int) $lineAmount->getAmount();
            $lineTotalConverted = $ledger->convertCurrency($exchanger, $payment->currency, $date, $lineTotal);

            // Only invoice credit note and convenience fee applications are considered
            if (VendorPaymentItemTypes::ConvenienceFee === $paymentItem->type) {
                // An amount without a document reduces A/P but is not applied to any particular document
                $entries[] = new LedgerEntry(
                    account: ApAccounts::ConvenienceFee->value,
                    amount: new Debit($lineTotalConverted, $lineTotal),
                    party: $vendor,
                );
                $remaining = $remaining->subtract($lineAmount);
            } elseif ($paymentItem->bill_id) {
                $documentId = $ledger->documents->getId(DocumentType::Invoice, (string) $paymentItem->bill_id);
                if ($documentId) {
                    $entries[] = new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Debit($lineTotalConverted, $lineTotal),
                        party: $vendor,
                        documentId: $documentId,
                    );
                    $remaining = $remaining->subtract($lineAmount);
                }
            } elseif ($paymentItem->vendor_credit_id) {
                $documentId = $ledger->documents->getId(DocumentType::CreditNote, (string) $paymentItem->vendor_credit_id);
                if ($documentId) {
                    $entries[] = new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Credit($lineTotalConverted, $lineTotal),
                        party: $vendor,
                        documentId: $documentId,
                    );
                    $remaining = $remaining->add($lineAmount);
                }
            }
        }

        // Any remaining amount reduces A/P but is not applied to any particular document.
        // If the remaining amount is negative then that means that the payment amount was over-applied.
        if (!$remaining->isZero()) {
            if ($remaining->isNegative()) {
                $formatter = new DecimalMoneyFormatter(new ISOCurrencies());

                throw new LedgerException('The payment amount applied exceeds the amount available by '.$formatter->format($remaining->negative()));
            }

            $remaining = (int) $remaining->getAmount();
            $remainingConverted = $ledger->convertCurrency($exchanger, $payment->currency, $date, $remaining);
            $entries[] = new LedgerEntry(
                account: ApAccounts::AccountsPayable->value,
                amount: new Debit($remainingConverted, $remaining),
                party: $vendor,
            );
        }

        $transactions[] = new Transaction(
            date: $date,
            currency: $payment->currency,
            entries: $entries,
        );

        $ledger->syncDocument($document, $transactions);
    }

    /**
     * @throws LedgerException
     */
    public function syncAdjustment(Ledger $ledger, VendorAdjustment $adjustment): void
    {
        $vendor = new AccountingVendor($adjustment->vendor_id);
        $date = new CarbonImmutable($adjustment->date);
        $document = new Document(
            type: DocumentType::Adjustment,
            reference: (string) $adjustment->id,
            party: $vendor,
            date: $date,
        );

        if ($adjustment->voided) {
            $ledger->voidDocument($document);

            return;
        }

        $transactions = [];
        $exchanger = $this->exchanger;
        $currencies = new ISOCurrencies();
        $moneyParser = new DecimalMoneyParser($currencies);
        $currency = new Currency($adjustment->currency); /* @phpstan-ignore-line */
        $total = (int) $moneyParser->parse((string) $adjustment->amount, $currency)->getAmount();
        $totalConverted = $ledger->convertCurrency($exchanger, $adjustment->currency, $date, $total);
        $entries = [];

        // Only invoice and credit note applications are considered
        if ($adjustment->bill_id) {
            $documentId = $ledger->documents->getId(DocumentType::Invoice, (string) $adjustment->bill_id);
            if ($documentId) {
                $entries = [
                    new LedgerEntry(
                        account: ApAccounts::Cash->value,
                        amount: new Credit($totalConverted, $total),
                        party: $vendor,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Debit($totalConverted, $total),
                        party: $vendor,
                        documentId: $documentId,
                    ),
                ];
            }
        } elseif ($adjustment->vendor_credit_id) {
            $documentId = $ledger->documents->getId(DocumentType::CreditNote, (string) $adjustment->vendor_credit_id);
            if ($documentId) {
                $entries = [
                    new LedgerEntry(
                        account: ApAccounts::Cash->value,
                        amount: new Credit($totalConverted, $total),
                        party: $vendor,
                    ),
                    new LedgerEntry(
                        account: ApAccounts::AccountsPayable->value,
                        amount: new Debit($totalConverted, $total),
                        party: $vendor,
                        documentId: $documentId,
                    ),
                ];
            }
        } else {
            // An amount without a document reduces A/P but is not applied to any particular document
            $entries = [
                new LedgerEntry(
                    account: ApAccounts::Cash->value,
                    amount: new Credit($totalConverted, $total),
                    party: $vendor,
                ),
                new LedgerEntry(
                    account: ApAccounts::AccountsPayable->value,
                    amount: new Debit($totalConverted, $total),
                    party: $vendor,
                ),
            ];
        }

        $transactions[] = new Transaction(
            date: $date,
            currency: $adjustment->currency,
            entries: $entries,
        );

        $ledger->syncDocument($document, $transactions);
    }
}
