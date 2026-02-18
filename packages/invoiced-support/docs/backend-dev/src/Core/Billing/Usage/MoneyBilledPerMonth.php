<?php

namespace App\Core\Billing\Usage;

use App\Companies\Models\Company;
use App\Core\Billing\Interfaces\BillingPeriodInterface;
use App\Core\Billing\Models\AbstractUsageRecord;
use App\Core\Billing\Models\MoneyBilledUsageRecord;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Core\I18n\CurrencyConverter;
use App\Core\I18n\ValueObjects\Money;
use Doctrine\DBAL\Connection;

/**
 * Gets the total $ billed during the period.
 */
class MoneyBilledPerMonth extends AbstractRecordBasedUsage
{
    public function __construct(
        private CurrencyConverter $converter,
        private Connection $database,
    ) {
    }

    public function supports(BillingPeriodInterface $billingPeriod): bool
    {
        return $billingPeriod instanceof MonthBillingPeriod;
    }

    public function canSendOverageNotification(): bool
    {
        // There should never be a notification about money billed per month usage.
        return false;
    }

    protected function getCount(Company $company, BillingPeriodInterface $billingPeriod): int
    {
        // check for A/R feature flag
        if (!$company->features->has('accounts_receivable')) {
            return 0;
        }

        // ADD: Invoices issued in period
        $query = 'SELECT currency,SUM(total) as total FROM Invoices WHERE tenant_id=:tenantId AND draft=0 AND voided=0 AND `created_at` BETWEEN :start AND :end GROUP BY currency';
        $rows = $this->database->fetchAllAssociative($query, [
            'tenantId' => $company->id,
            'start' => $billingPeriod->getStart()->toDateTimeString(),
            'end' => $billingPeriod->getEnd()->toDateTimeString(),
        ]);
        $total = $this->addValues($rows);

        // SUBTRACT: Invoices voided in period
        $query = 'SELECT currency,SUM(total) as total FROM Invoices WHERE tenant_id=:tenantId AND draft=0 AND voided=1 AND date_voided BETWEEN :start AND :end GROUP BY currency';
        $rows = $this->database->fetchAllAssociative($query, [
            'tenantId' => $company->id,
            'start' => $billingPeriod->getStart()->getTimestamp(),
            'end' => $billingPeriod->getEnd()->getTimestamp(),
        ]);
        $total = $total->subtract($this->addValues($rows));

        // SUBTRACT: Credit notes issued in period
        $query = 'SELECT currency,SUM(total) as total FROM CreditNotes WHERE tenant_id=:tenantId AND draft=0 AND voided=0 AND `created_at` BETWEEN :start AND :end GROUP BY currency';
        $rows = $this->database->fetchAllAssociative($query, [
            'tenantId' => $company->id,
            'start' => $billingPeriod->getStart()->toDateTimeString(),
            'end' => $billingPeriod->getEnd()->toDateTimeString(),
        ]);
        $total = $total->subtract($this->addValues($rows));

        // ADD: Credit notes voided in period
        $query = 'SELECT currency,SUM(total) as total FROM CreditNotes WHERE tenant_id=:tenantId AND draft=0 AND voided=1 AND date_voided BETWEEN :start AND :end GROUP BY currency';
        $rows = $this->database->fetchAllAssociative($query, [
            'tenantId' => $company->id,
            'start' => $billingPeriod->getStart()->getTimestamp(),
            'end' => $billingPeriod->getEnd()->getTimestamp(),
        ]);
        $total = $total->add($this->addValues($rows));

        // SUBTRACT: Credit balance adjustments in period
        $query = "SELECT currency,SUM(amount) as total FROM Transactions WHERE tenant_id=:tenantId AND `type`='adjustment' AND method='balance' AND `date` BETWEEN :start AND :end GROUP BY currency";
        $rows = $this->database->fetchAllAssociative($query, [
            'tenantId' => $company->id,
            'start' => $billingPeriod->getStart()->getTimestamp(),
            'end' => $billingPeriod->getEnd()->getTimestamp(),
        ]);
        $total = $total->add($this->addValues($rows)); // Add because amount is negative if a credit

        return (int) max(0, ceil($total->toDecimal())); // round up to the next whole $ amount, cannot be negative
    }

    private function addValues(array $rows): Money
    {
        $total = Money::zero('usd');
        foreach ($rows as $row) {
            $amount = Money::fromDecimal($row['currency'], $row['total']);
            $converted = $this->converter->convert($amount, 'usd');
            $total = $total->add($converted);
        }

        return $total;
    }

    /**
     * @param MonthBillingPeriod $billingPeriod
     */
    public function getUsageRecord(Company $company, BillingPeriodInterface $billingPeriod): AbstractUsageRecord
    {
        return MoneyBilledUsageRecord::getOrCreate($company, $billingPeriod);
    }
}
