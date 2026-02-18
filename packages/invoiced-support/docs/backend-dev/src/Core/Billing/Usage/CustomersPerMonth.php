<?php

namespace App\Core\Billing\Usage;

use App\Companies\Models\Company;
use App\Core\Billing\Interfaces\BillingPeriodInterface;
use App\Core\Billing\Models\CustomerUsageRecord;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use Doctrine\DBAL\Connection;

/**
 * Tracks the # of active customers billed during the period.
 * This counts customers with:
 *  1. an invoice, or
 *  2. an estimate, or
 *  3. a payment, or
 *  4. an active subscription.
 */
class CustomersPerMonth extends AbstractRecordBasedUsage
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

        $query = "SELECT COUNT(*)
FROM Customers
WHERE tenant_id = {$company->id()}
  AND (EXISTS(SELECT 1
              FROM Invoices
              WHERE customer = Customers.id AND draft = 0 AND voided = 0 AND `created_at` BETWEEN :start AND :end) OR
       EXISTS(SELECT 1 FROM Payments WHERE customer = Customers.id AND `created_at` BETWEEN :start AND :end) OR
       EXISTS(SELECT 1 FROM Subscriptions WHERE customer = Customers.id AND finished = 0 AND canceled = 0) OR
       EXISTS(SELECT 1
              FROM Estimates
              WHERE customer = Customers.id AND draft = 0 AND `created_at` BETWEEN :start AND :end))";

        return (int) $this->database->fetchOne($query, [
            'start' => $billingPeriod->getStart()->toDateTimeString(),
            'end' => $billingPeriod->getEnd()->toDateTimeString(),
        ]);
    }

    /**
     * @param MonthBillingPeriod $billingPeriod
     */
    public function getUsageRecord(Company $company, BillingPeriodInterface $billingPeriod): CustomerUsageRecord
    {
        return CustomerUsageRecord::getOrCreate($company, $billingPeriod);
    }
}
