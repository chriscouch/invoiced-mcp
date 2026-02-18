<?php

namespace App\Reports\DashboardMetrics;

use App\Companies\Models\Member;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class DaysSalesOutstandingMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'days_sales_outstanding';
    }

    public function __construct(private Connection $database, ReportHelper $helper)
    {
        parent::__construct($helper);
    }

    public function build(DashboardContext $context, array $options): array
    {
        $this->setContext($context);

        // This is used to exclude future invoices from the current
        // outstanding amounts.
        $date = CarbonImmutable::now()->endOfDay();
        $currency = $options['currency'] ?? $context->company->currency;

        return [
            'dso' => $this->daysSalesOutstanding($context, $currency, $date),
        ];
    }

    public function invalidateCacheAfterEvent(): bool
    {
        return false;
    }

    public function getExpiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->endOfDay();
    }

    /**
     * Gets the Days Sales Outstanding (DSO) metric.
     */
    private function daysSalesOutstanding(DashboardContext $context, string $currency, CarbonImmutable $date): int
    {
        // get the total amount invoiced over the period
        // If an account has < 365 days of history then use
        // the company creation date to determine how many days of sales we have
        $startDate = $context->company->created_at;

        $daysSinceStart = max(1, ceil((time() - $startDate) / 86400));
        $daysInPeriod = (int) min(365, $daysSinceStart);

        $totalSales = $this->getTotalSales($context, $currency, $daysInPeriod, $date);

        // if there are no paid invoices then this metric is not available
        if ($totalSales->isZero()) {
            return -1;
        }

        $outstanding = $this->getOutstandingBalance($context, $currency, $date);

        // DSO = Accounts Receivable / Total Sales * Days In Period
        return (int) round($outstanding->toDecimal() / $totalSales->toDecimal() * $daysInPeriod);
    }

    private function getOutstandingBalance(DashboardContext $context, string $currency, CarbonImmutable $date): Money
    {
        // Open Invoices
        $query = $this->database->createQueryBuilder()
            ->select('sum(balance)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $context->company->id())
            ->andWhere('date <= '.$date->getTimestamp())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('paid = 0')
            ->andWhere('draft = 0')
            ->andWhere('closed = 0')
            ->andWhere('voided = 0');

        $this->addCustomerRestriction($query, 'Invoices.customer');

        $invoices = (float) $query->fetchOne();
        $invoiceBalance = Money::fromDecimal($currency, $invoices);

        // Open Credit Notes
        $query = $this->database->createQueryBuilder()
            ->select('sum(balance)')
            ->from('CreditNotes')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $context->company->id())
            ->andWhere('date <= '.$date->getTimestamp())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('paid = 0')
            ->andWhere('draft = 0')
            ->andWhere('closed = 0')
            ->andWhere('voided = 0');

        $this->addCustomerRestriction($query, 'CreditNotes.customer');

        $creditNotes = (float) $query->fetchOne();
        $creditNoteBalance = Money::fromDecimal($currency, $creditNotes);

        return $invoiceBalance->subtract($creditNoteBalance);
    }

    /**
     * Gets the sales total amount over the period.
     */
    private function getTotalSales(DashboardContext $context, string $currency, int $daysInPeriod, CarbonImmutable $date): Money
    {
        $start = $date->subDays($daysInPeriod);

        // Total Invoices in Period
        $query = $this->database->createQueryBuilder()
            ->select('sum(total)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $context->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('draft = 0')
            ->andWhere('voided = 0')
            ->andWhere('date BETWEEN '.$start->getTimestamp().' AND '.$date->getTimestamp());

        $this->addCustomerRestriction($query, 'Invoices.customer');

        $invoices = (float) $query->fetchOne();
        $invoiceTotal = Money::fromDecimal($currency, $invoices);

        // Total Credit Notes in Period
        $query = $this->database->createQueryBuilder()
            ->select('sum(total)')
            ->from('CreditNotes')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $context->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('draft = 0')
            ->andWhere('voided = 0')
            ->andWhere('date BETWEEN '.$start->getTimestamp().' AND '.$date->getTimestamp());

        $this->addCustomerRestriction($query, 'CreditNotes.customer');

        $creditNotes = (float) $query->fetchOne();
        $creditNoteTotal = Money::fromDecimal($currency, $creditNotes);

        return $invoiceTotal->subtract($creditNoteTotal);
    }

    private function addCustomerRestriction(QueryBuilder $query, string $customerIdColumn): void
    {
        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql($customerIdColumn)) {
                    $query->andWhere($restriction);
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $query->andWhere('customer IN (SELECT id FROM Customers WHERE tenant_id='.$this->company->id().' AND owner_id='.$this->member->user_id.')');
            }
        }

        if ($this->customer) {
            $query->andWhere('customer = :customer')
                ->setParameter('customer', $this->customer->id());
        }
    }
}
