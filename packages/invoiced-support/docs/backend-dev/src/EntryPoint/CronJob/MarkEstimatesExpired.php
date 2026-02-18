<?php

namespace App\EntryPoint\CronJob;

use App\AccountsReceivable\Libs\AbstractReceivableDocumentStatusJob;
use App\AccountsReceivable\Models\Estimate;
use App\Companies\Models\Company;

/**
 * Updates status on any estimates that have expired.
 */
class MarkEstimatesExpired extends AbstractReceivableDocumentStatusJob
{
    public function getCompanies(): array
    {
        return $this->database->fetchFirstColumn('SELECT tenant_id FROM Estimates JOIN Companies ON Companies.id=tenant_id WHERE Companies.canceled=0 AND status NOT IN ("voided","draft","invoiced","approved","declined","expired") AND expiration_date < ? AND expiration_date IS NOT NULL GROUP BY tenant_id', [time()]);
    }

    public function getDocuments(Company $company): array
    {
        return Estimate::queryWithTenant($company)
            ->where('expiration_date', time(), '<')
            ->where('expiration_date', null, '<>')
            ->where('status', ['voided', 'draft', 'invoiced', 'approved', 'declined', 'expired'], '<>')
            ->first(1000);
    }
}
