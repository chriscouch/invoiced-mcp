<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Models\CreditBalance;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Enums\PaymentAmountOption;
use App\PaymentProcessing\Exceptions\FormException;

class PaymentAmountCalculator
{
    /**
     * Calculates the amount of a payment choice. If a choice
     * does not have a preset amount, e.g. a partial payment,
     * then a null value is returned.
     *
     * @throws FormException if the option is not valid for the given document type
     */
    public function calculate(?ReceivableDocument $document, PaymentAmountOption $option, ?Customer $customer): ?Money
    {
        if ($document instanceof Invoice) {
            return $this->calcForInvoice($document, $option);
        }

        if ($document instanceof Estimate) {
            return $this->calcForEstimate($document, $option);
        }

        if ($document instanceof CreditNote) {
            return $this->calcForCreditNote($document, $option);
        }

        if (!$document && $customer) {
            return $this->calcForCreditBalance($customer, $option);
        } elseif (!$document && !$customer) { /* @phpstan-ignore-line */
            $this->calcForAdvancePayment($option);

            return null;
        }

        throw new FormException('Amount option not supported: '.$option->name);
    }

    private function calcForCreditNote(CreditNote $creditNote, PaymentAmountOption $option): Money
    {
        if (PaymentAmountOption::ApplyCredit == $option) {
            return Money::fromDecimal($creditNote->currency, $creditNote->balance ?? 0)->negated();
        }

        throw new FormException('Payment option not allowed on credit note # '.$creditNote->number.': '.$option->name);
    }

    private function calcForEstimate(Estimate $estimate, PaymentAmountOption $option): Money
    {
        if (PaymentAmountOption::PayInFull == $option) {
            return $estimate->getDepositBalance();
        }

        throw new FormException('Payment option not allowed on estimate # '.$estimate->number.': '.$option->name);
    }

    private function calcForInvoice(Invoice $invoice, PaymentAmountOption $option): ?Money
    {
        if (PaymentAmountOption::PayPartial == $option) {
            return null;
        }

        // if the invoice is pending then can only pay
        // the remaining amount that's not pending
        if (InvoiceStatus::Pending->value == $invoice->status) {
            $database = Invoice::getDriver()->getConnection(null);
            $_amount = $database->createQueryBuilder()
                ->select('sum(amount)')
                ->from('Transactions')
                ->where('tenant_id = :tenantId')
                ->setParameter('tenantId', $invoice->tenant_id)
                ->andWhere('invoice = :invoiceId')
                ->setParameter('invoiceId', $invoice->id())
                ->andWhere('status = "'.Transaction::STATUS_PENDING.'"')
                ->andWhere('type IN ("'.Transaction::TYPE_CHARGE.'", "'.Transaction::TYPE_PAYMENT.'")')
                ->fetchOne();

            $pendingAmount = Money::fromDecimal($invoice->currency, $_amount);
        } else {
            $pendingAmount = Money::zero($invoice->currency);
        }

        if (PaymentAmountOption::PayInFull == $option) {
            return Money::fromDecimal($invoice->currency, $invoice->balance ?? 0)->subtract($pendingAmount);
        }

        if (PaymentAmountOption::PaymentPlan == $option && $paymentPlan = $invoice->paymentPlan()) {
            $installmentData = $paymentPlan->calculateBalance();

            return Money::fromDecimal($invoice->currency, $installmentData['balance'])->subtract($pendingAmount);
        }

        throw new FormException('Payment option not allowed on invoice # '.$invoice->number.': '.$option->name);
    }

    private function calcForCreditBalance(Customer $customer, PaymentAmountOption $option): Money
    {
        if (PaymentAmountOption::ApplyCredit == $option) {
            return CreditBalance::lookup($customer)->negated();
        }

        throw new FormException('Payment option not allowed on credit balance: '.$option->name);
    }

    private function calcForAdvancePayment(PaymentAmountOption $option): void
    {
        if (PaymentAmountOption::AdvancePayment == $option) {
            return;
        }

        throw new FormException('Payment option not allowed on advance payment: '.$option->name);
    }
}
