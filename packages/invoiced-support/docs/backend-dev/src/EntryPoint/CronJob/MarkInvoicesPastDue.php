<?php

namespace App\EntryPoint\CronJob;

use App\AccountsReceivable\Libs\AbstractReceivableDocumentStatusJob;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;

/**
 * Marks any invoices that have become past due.
 */
class MarkInvoicesPastDue extends AbstractReceivableDocumentStatusJob
{
    public function getCompanies(): array
    {
        return $this->database->fetchFirstColumn('SELECT tenant_id FROM Invoices JOIN Companies ON Companies.id=tenant_id WHERE Companies.canceled=0 AND status IN ("not_sent","sent","viewed") AND due_date < ? AND due_date IS NOT NULL GROUP BY tenant_id', [time()]);
    }

    public function getDocuments(Company $company): array
    {
        return Invoice::queryWithTenant($company)
            ->where('status IN ("not_sent","sent","viewed")')
            ->where('due_date', time(), '<')
            ->where('due_date', null, '<>')
            ->first(1000);
    }
}
