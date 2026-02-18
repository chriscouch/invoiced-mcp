<?php

namespace App\Core\Billing\Usage;

use App\Companies\Models\Company;
use App\Core\Billing\Interfaces\BillingPeriodInterface;
use App\Core\Billing\Models\InvoiceUsageRecord;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use Doctrine\DBAL\Connection;

/**
 * Calculates the number of issued invoices during the period.
 */
class InvoicesPerMonth extends AbstractRecordBasedUsage
{
    public function __construct(private Connection $database)
    {
    }

    public function supports(BillingPeriodInterface $billingPeriod): bool
    {
        return $billingPeriod instanceof MonthBillingPeriod;
    }

    public function canSendOverageNotification(): bool
    {
        return true;
    }

    protected function getCount(Company $company, BillingPeriodInterface $billingPeriod): int
    {
        // check for A/R feature flag
        if (!$company->features->has('accounts_receivable')) {
            return 0;
        }

        $query = "SELECT COUNT(*) FROM Invoices WHERE tenant_id={$company->id()} AND draft=0 AND voided=0 AND `created_at` BETWEEN :start AND :end";

        return (int) $this->database->fetchOne($query, [
            'start' => $billingPeriod->getStart()->toDateTimeString(),
            'end' => $billingPeriod->getEnd()->toDateTimeString(),
        ]);
    }

    /**
     * @param MonthBillingPeriod $billingPeriod
     */
    public function getUsageRecord(Company $company, BillingPeriodInterface $billingPeriod): InvoiceUsageRecord
    {
        return InvoiceUsageRecord::getOrCreate($company, $billingPeriod);
    }
}
