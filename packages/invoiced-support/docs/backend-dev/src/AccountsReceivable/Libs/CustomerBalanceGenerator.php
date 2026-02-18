<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\ValueObjects\CustomerBalance;
use App\CashApplication\Models\CreditBalance;
use App\Core\I18n\ValueObjects\Money;
use Doctrine\DBAL\Connection;

final class CustomerBalanceGenerator
{
    public function __construct(private Connection $database)
    {
    }

    public function generate(Customer $customer, ?string $currency = null): CustomerBalance
    {
        if (!$currency) {
            $currency = $customer->calculatePrimaryCurrency();
        }

        return new CustomerBalance(
            currency: $currency,
            totalOutstanding: $this->totalOutstanding($customer, $currency),
            dueNow: $this->dueNow($customer, $currency),
            pastDue: $this->isPastDue($customer),
            openCreditNotes: $this->openCreditNotes($customer, $currency),
            availableCredits: CreditBalance::lookup($customer, $currency),
            history: $this->getCreditBalanceHistory($customer, $currency),
        );
    }

    /**
     * Gets the recent credit balance history.
     */
    private function getCreditBalanceHistory(Customer $customer, string $currency): array
    {
        $history = [];

        $balances = CreditBalance::where('customer_id', $customer->id())
            ->where('currency', $currency)
            ->first(100);
        foreach ($balances as $balance) {
            $history[] = $balance->toArray();
        }

        return $history;
    }

    /**
     * Checks if a customer's account is past due.
     */
    private function isPastDue(Customer $customer): bool
    {
        // NOTE Not checking currency here because
        // we want to know if the account has ANY past due
        // invoices, not just past due invoices for the
        // given currency.

        return $this->database->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('Invoices')
            ->andWhere('customer = '.$customer->id())
            ->andWhere('draft = 0')
            ->andWhere('voided = 0')
            ->andWhere('status = "'.InvoiceStatus::PastDue->value.'"')
            ->fetchOne() > 0;
    }

    /**
     * Gets the total outstanding for a customer account.
     */
    public function totalOutstanding(Customer $customer, string $currency): Money
    {
        $balance = (float) $this->database->createQueryBuilder()
            ->select('sum(balance)')
            ->from('Invoices')
            ->andWhere('date <= '.time())
            ->andWhere('customer = '.$customer->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('paid = 0')
            ->andWhere('draft = 0')
            ->andWhere('closed = 0')
            ->andWhere('voided = 0')
            ->fetchOne();

        return Money::fromDecimal($currency, $balance);
    }

    /**
     * Gets the amount currently due for a customer account. More specifically,
     * this is the amount the customer needs to submit a payment for.
     *
     * This amount can be different from the total outstanding if:
     * 1) there is a payment plan with installments not due yet, or
     * 2) non-past due AutoPay invoices are not counted
     */
    private function dueNow(Customer $customer, string $currency): Money
    {
        // Add invoices that meet these conditions:
        // 1) already issued and outstanding (no payment plan or AutoPay), and
        // 2) have AutoPay enabled but past due (no payment plan), and
        // 3) have a payment plan and no AutoPay - only past due installments are considered as due now
        $balance = (float) $this->database->createQueryBuilder()
            ->select('sum(balance)')
            ->from('Invoices')
            ->andWhere('date <= '.time())
            ->andWhere('customer = '.$customer->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('paid = 0')
            ->andWhere('draft = 0')
            ->andWhere('closed = 0')
            ->andWhere('voided = 0')
            ->andWhere('status <> "'.InvoiceStatus::Pending->value.'"')
            ->andWhere('autopay = 0 OR status = "'.InvoiceStatus::PastDue->value.'"')
            ->andWhere('payment_plan_id IS NULL')
            ->fetchOne();
        $balance = Money::fromDecimal($currency, $balance);

        // add in non-AutoPay payment plans
        $installmentBalance = (float) $this->database->createQueryBuilder()
            ->select('sum(installment.balance)')
            ->from('PaymentPlanInstallments', 'installment')
            ->join('installment', 'PaymentPlans', 'p', 'payment_plan_id=p.id')
            ->join('p', 'Invoices', 'i', 'invoice_id=i.id')
            ->andWhere('installment.date <= '.time())
            ->andWhere('i.customer = '.$customer->id())
            ->andWhere('i.currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('i.paid = 0')
            ->andWhere('i.draft = 0')
            ->andWhere('i.closed = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('i.autopay = 0')
            ->andWhere('i.status <> "'.InvoiceStatus::Pending->value.'"')
            ->fetchOne();
        $installmentBalance = Money::fromDecimal($currency, $installmentBalance);

        return $balance->add($installmentBalance);
    }

    /**
     * Gets the total open credit notes for a customer account.
     */
    public function openCreditNotes(Customer $customer, string $currency): Money
    {
        $balance = (float) $this->database->createQueryBuilder()
            ->select('sum(balance)')
            ->from('CreditNotes')
            ->andWhere('date <= '.time())
            ->andWhere('customer = '.$customer->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('paid = 0')
            ->andWhere('draft = 0')
            ->andWhere('closed = 0')
            ->andWhere('voided = 0')
            ->fetchOne();

        return Money::fromDecimal($currency, $balance);
    }
}
