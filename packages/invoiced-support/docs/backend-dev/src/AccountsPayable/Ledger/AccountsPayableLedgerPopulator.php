<?php

namespace App\AccountsPayable\Ledger;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\VendorAdjustment;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Models\VendorPayment;
use App\Companies\Models\Company;
use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Ledger\Ledger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Syncs the accounts payable ledger using a company's data.
 */
class AccountsPayableLedgerPopulator
{
    public function __construct(
        private AccountsPayableLedger $accountsPayableLedger,
    ) {
    }

    /**
     * This populates a new or existing accounts payable ledger
     * for a company using the company's transaction data. This
     * is not intended to be used in normal circumstances. It can
     * be used to rebuild a ledger when it is missing or if an
     * error needs to be corrected. This method is not efficient to
     * run, especially for an account with a large data set.
     *
     * @throws LedgerException
     */
    public function populateLedger(Company $company, ?OutputInterface $output = null): Ledger
    {
        $ledger = $this->accountsPayableLedger->getLedger($company);
        $output?->writeln('Building ledger # '.$ledger->id.' for company # '.$company->id);

        $this->syncBills($ledger, $output);
        $this->syncVendorCredits($ledger, $output);
        $this->syncVendorPayments($ledger, $output);
        $this->syncVendorAdjustments($ledger, $output);

        return $ledger;
    }

    private function syncBills(Ledger $ledger, ?OutputInterface $output): void
    {
        $n = 0;
        $billReferences = [];
        $bills = Bill::all();
        foreach ($bills as $bill) {
            $this->accountsPayableLedger->syncBill($ledger, $bill);
            $billReferences[] = (string) $bill->id;
            ++$n;
        }
        $ledger->voidRemainingDocuments(DocumentType::Invoice, $billReferences);
        $output?->writeln('Synced '.$n.' bills');
    }

    private function syncVendorCredits(Ledger $ledger, ?OutputInterface $output): void
    {
        $n = 0;
        $creditReferences = [];
        $vendorCredits = VendorCredit::all();
        foreach ($vendorCredits as $vendorCredit) {
            $this->accountsPayableLedger->syncVendorCredit($ledger, $vendorCredit);
            $creditReferences[] = (string) $vendorCredit->id;
            ++$n;
        }
        $ledger->voidRemainingDocuments(DocumentType::CreditNote, $creditReferences);
        $output?->writeln('Synced '.$n.' vendor credits');
    }

    private function syncVendorPayments(Ledger $ledger, ?OutputInterface $output): void
    {
        $n = 0;
        $payments = VendorPayment::all();
        $paymentReferences = [];
        foreach ($payments as $payment) {
            $this->accountsPayableLedger->syncPayment($ledger, $payment);
            $paymentReferences[] = (string) $payment->id;
            ++$n;
        }
        $ledger->voidRemainingDocuments(DocumentType::Payment, $paymentReferences);
        $output?->writeln('Synced '.$n.' payments');
    }

    private function syncVendorAdjustments(Ledger $ledger, ?OutputInterface $output): void
    {
        $n = 0;
        $adjustmentReferences = [];
        $adjustments = VendorAdjustment::all();
        foreach ($adjustments as $adjustment) {
            $this->accountsPayableLedger->syncAdjustment($ledger, $adjustment);
            $adjustmentReferences[] = (string) $adjustment->id;
            ++$n;
        }
        $ledger->voidRemainingDocuments(DocumentType::Adjustment, $adjustmentReferences);
        $output?->writeln('Synced '.$n.' adjustments');
    }
}
