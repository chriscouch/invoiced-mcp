<?php

namespace App\EntryPoint\CronJob;

use App\AccountsReceivable\Libs\AbstractReceivableDocumentStatusJob;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;

/**
 * Marks any payment plan invoices that have become past due.
 */
class MarkPaymentPlanInvoicesPastDue extends AbstractReceivableDocumentStatusJob
{
    public function getCompanies(): array
    {
        return $this->database->fetchFirstColumn('SELECT tenant_id FROM Invoices JOIN Companies ON Companies.id=tenant_id WHERE Companies.canceled=0 AND status IN ("not_sent","sent","viewed") AND payment_plan_id IS NOT NULL AND EXISTS (SELECT 1 FROM PaymentPlanInstallments WHERE payment_plan_id=Invoices.payment_plan_id and balance > 0 and `date` < ?) GROUP BY tenant_id', [time()]);
    }

    public function getDocuments(Company $company): array
    {
        return Invoice::queryWithTenant($company)
            ->where('status IN ("not_sent","sent","viewed")')
            ->where('payment_plan_id IS NOT NULL')
            ->where('EXISTS (SELECT 1 FROM PaymentPlanInstallments WHERE payment_plan_id=Invoices.payment_plan_id and balance > 0 and `date` < '.time().')')
            ->first(1000);
    }
}
