<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\ValueObjects\CreditNoteStatus;
use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\Companies\Models\Company;
use App\Core\Orm\Query;
use App\PaymentPlans\Models\PaymentPlan;
use Carbon\CarbonImmutable;

/**
 * Sends out newly issued documents (estimates, invoices, payment plans, etc.)
 * to the customer via email.
 */
class IssuedDocumentNotifier
{
    const DELAY_FROM_ISSUE = 300; // 5 minutes

    /**
     * Gets all the un-sent, newly issued credit notes. This
     * includes credit notes that are paid or closed, but not
     * future-dated credit notes.
     *
     * @return Query<CreditNote>
     */
    public function getCreditNotes(Company $company): Query
    {
        $issuedBefore = time() - self::DELAY_FROM_ISSUE;

        return CreditNote::queryWithTenant($company)
            ->join(Customer::class, 'customer', 'Customers.id')
            ->where('date', $issuedBefore, '<=')
            ->where('updated_at', CarbonImmutable::createFromTimestamp($issuedBefore), '<=')
            ->where('sent', false)
            ->where('status', [
                CreditNoteStatus::VOIDED,
                CreditNoteStatus::DRAFT,
            ], '<>')
            ->where('Customers.email IS NOT NULL');
    }

    /**
     * Gets all the newly issued estimates or those
     * that need a reminder. This excludes estimates that
     * are closed and future-dated estimates.
     *
     * @param int $reminderDays sends a reminder every N days if N > 0
     *
     * @return Query<Estimate>|null
     */
    public function getEstimates(Company $company, bool $sendNewlyIssued, int $reminderDays): ?Query
    {
        $issuedBefore = time() - self::DELAY_FROM_ISSUE;

        $sentCondition = [];
        if ($sendNewlyIssued) {
            $sentCondition[] = 'sent = 0';
        }

        if ($reminderDays > 0) {
            $lastReminder = time() - $reminderDays * 86400;
            $sentCondition[] = "(`date` <= $lastReminder AND last_sent <= $lastReminder)";
        }

        if (!$sentCondition) {
            return null;
        }

        return Estimate::queryWithTenant($company)
            ->join(Customer::class, 'customer', 'Customers.id')
            ->where('date', $issuedBefore, '<=')
            ->where('updated_at', CarbonImmutable::createFromTimestamp($issuedBefore), '<=')
            ->where('('.join(' OR ', $sentCondition).')')
            ->where('status', [
                EstimateStatus::VOIDED,
                EstimateStatus::DRAFT,
                EstimateStatus::INVOICED,
                EstimateStatus::APPROVED,
                EstimateStatus::DECLINED,
                EstimateStatus::EXPIRED,
            ], '<>')
            ->where('Customers.email IS NOT NULL');
    }

    /**
     * Gets all the un-sent, newly issued invoices or those
     * that need a reminder. This excludes invoices that
     * are closed/paid and future-dated invoices.
     *
     * @param int $reminderDays sends a reminder every N days if N > 0
     *
     * @return Query<Invoice>|null
     */
    public function getInvoices(Company $company, bool $sendNewlyIssued, int $reminderDays, bool $sendOnAutoPay, ?int $cutoffDate = null): ?Query
    {
        $sentCondition = [];
        if ($sendNewlyIssued) {
            $sentCondition[] = 'sent = 0';
        }

        if ($reminderDays > 0) {
            $lastReminder = time() - $reminderDays * 86400;
            $sentCondition[] = "(`date` <= $lastReminder AND last_sent <= $lastReminder)";
        }

        if (!$sentCondition) {
            return null;
        }

        $issuedBefore = time() - self::DELAY_FROM_ISSUE;
        $query = Invoice::queryWithTenant($company)
            ->join(Customer::class, 'customer', 'Customers.id')
            ->where('date', $issuedBefore, '<=')
            ->where('updated_at', CarbonImmutable::createFromTimestamp($issuedBefore), '<=')
            ->where('('.join(' OR ', $sentCondition).')')
            ->where('status', [
                InvoiceStatus::Voided->value,
                InvoiceStatus::Draft->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::BadDebt->value,
                InvoiceStatus::Pending->value,
            ], '<>')
            ->where('payment_plan_id IS NULL')
            ->where('Customers.email IS NOT NULL');

        if (!$sendOnAutoPay) {
            $query->where('(Invoices.autopay=0 OR Customers.default_source_id IS NULL)');
        }

        if ($cutoffDate > 0) {
            $query->where('date', $cutoffDate, '>');
        }

        if ($company->features->has('smart_chasing')) {
            $tenantId = (string) $company->id();
            $invoiceDeliveries = "SELECT Invoices.id FROM Invoices INNER JOIN InvoiceDeliveries ON InvoiceDeliveries.invoice_id = Invoices.id WHERE InvoiceDeliveries.chase_schedule != '[]' AND InvoiceDeliveries.disabled = 0 AND Invoices.tenant_id = $tenantId";
            $query->where("Invoices.id NOT IN ($invoiceDeliveries)");
        }

        return $query;
    }

    /**
     * Gets all the newly issued invoices with payment plans
     * that need onboarding. This excludes invoices that are closed/paid
     * and future-dated invoices.
     *
     * @param int $reminderDays sends a reminder every N days if N > 0
     *
     * @return Query<Invoice>|null
     */
    public function getPaymentPlans(Company $company, bool $sendNewlyIssued, int $reminderDays): ?Query
    {
        $issuedBefore = time() - self::DELAY_FROM_ISSUE;

        $sentCondition = [];
        if ($sendNewlyIssued) {
            $sentCondition[] = 'sent = 0';
        }

        if ($reminderDays > 0) {
            $lastReminder = time() - $reminderDays * 86400;
            $sentCondition[] = "(`date` <= $lastReminder AND last_sent <= $lastReminder)";
        }

        if (!$sentCondition) {
            return null;
        }

        return Invoice::queryWithTenant($company)
            ->join(Customer::class, 'customer', 'Customers.id')
            ->join(PaymentPlan::class, 'payment_plan_id', 'PaymentPlans.id')
            ->where('date', $issuedBefore, '<=')
            ->where('updated_at', CarbonImmutable::createFromTimestamp($issuedBefore), '<=')
            ->where('('.join(' OR ', $sentCondition).')')
            ->where('status', [
                InvoiceStatus::Voided->value,
                InvoiceStatus::Draft->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::BadDebt->value,
            ], '<>')
            ->where('PaymentPlans.status', PaymentPlan::STATUS_PENDING_SIGNUP)
            ->where('Customers.email IS NOT NULL');
    }
}
