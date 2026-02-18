<?php

namespace App\Chasing\CustomerChasing;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Chasing\ValueObjects\ChasingBalance;
use App\Core\I18n\Exception\MismatchedCurrencyException;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentPlans\Models\PaymentPlan;

/**
 * Calculates account balance and aging according
 * to the rules for chasing (no AutoPay invoices).
 */
class ChasingBalanceGenerator
{
    /** @var Invoice[] */
    private array $invoices;
    private Money $balance;
    private Money $pastDueBalance;
    private int $age;
    private ?int $pastDueAge;

    public function generate(Customer $customer, string $currency): ChasingBalance
    {
        $this->age = 0;
        $this->pastDueAge = null;
        $this->invoices = [];
        $this->balance = new Money($currency, 0);
        $this->pastDueBalance = new Money($currency, 0);

        // process the open invoices
        $invoices = $this->fetchInvoices($customer, $currency);
        foreach ($invoices as $invoice) {
            if ($paymentPlan = $invoice->paymentPlan()) {
                $this->calculatePaymentPlanBalance($invoice, $paymentPlan);
            } else {
                $this->calculateInvoiceBalance($invoice);
            }
        }

        // fetch balance of open credit notes
        // which decrease both balance and past due balance
        // credit notes have no effect on account age
        $openCreditNotes = $this->openCreditNotes($customer, $currency);
        $this->balance = $this->balance->subtract($openCreditNotes);
        $this->pastDueBalance = $this->pastDueBalance->subtract($openCreditNotes);

        return new ChasingBalance(
            $customer,
            $this->invoices,
            $this->balance,
            $this->pastDueBalance,
            $this->age,
            $this->pastDueAge,
        );
    }

    /**
     * Calculate balance for invoices without a payment plan attached.
     *
     * @throws MismatchedCurrencyException
     */
    private function calculateInvoiceBalance(Invoice $invoice): void
    {
        // determine amount contributed to balance by this invoice
        $balance = Money::fromDecimal($invoice->currency, $invoice->balance);

        // deduct pending payments
        if (InvoiceStatus::Pending->value == $invoice->status) {
            $pendingTxns = Transaction::where('invoice', $invoice->id())
                ->where('status', Transaction::STATUS_PENDING)
                ->all();
            foreach ($pendingTxns as $txn) {
                $pendingAmount = Money::fromDecimal($txn->currency, $txn->amount);
                $balance = $balance->subtract($pendingAmount);
            }
        }

        if ($balance->isZero()) {
            return;
        }

        $this->invoices[] = $invoice;
        $this->balance = $this->balance->add($balance);
        $this->age = max($invoice->age, $this->age);

        if (InvoiceStatus::PastDue->value == $invoice->status) {
            $pastDueAge = $invoice->past_due_age;
            if (null === $this->pastDueAge || $pastDueAge > $this->pastDueAge) {
                $this->pastDueAge = $pastDueAge;
            }
            $this->pastDueBalance = $this->pastDueBalance->add($balance);
        }
    }

    /**
     * Calculate balance for invoices with payment plan attached.
     *
     * @throws MismatchedCurrencyException
     */
    private function calculatePaymentPlanBalance(Invoice $invoice, PaymentPlan $paymentPlan): void
    {
        $return = $paymentPlan->calculateBalance();

        if (0 === $return['balance']) {
            return;
        }

        $this->invoices[] = $invoice;
        $balance = Money::fromDecimal($invoice->currency, $return['balance']);
        $pastDueBalance = Money::fromDecimal($invoice->currency, $return['pastDueBalance']);
        $this->balance = $this->balance->add($balance);
        $this->age = max($return['age'], $this->age);
        $this->pastDueBalance = $this->pastDueBalance->add($pastDueBalance);
        if (null !== $return['pastDueAge']) {
            $this->pastDueAge = max($return['pastDueAge'], $this->pastDueAge);
        }
    }

    /**
     * @return Invoice[]
     */
    private function fetchInvoices(Customer $customer, string $currency): iterable
    {
        return Invoice::where('customer', $customer->id())
            ->where('paid', false)
            ->where('closed', false)
            ->where('draft', false)
            ->where('voided', false)
            ->where('date', time(), '<=')
            ->where('autopay', false)
            ->where('currency', $currency)
            ->sort('date ASC')
            ->all();
    }

    private function openCreditNotes(Customer $customer, string $currency): Money
    {
        $total = CreditNote::where('customer', $customer->id())
            ->where('paid', false)
            ->where('closed', false)
            ->where('draft', false)
            ->where('voided', false)
            ->where('date', time(), '<=')
            ->where('currency', $currency)
            ->sum('balance');

        return Money::fromDecimal($currency, $total);
    }
}
