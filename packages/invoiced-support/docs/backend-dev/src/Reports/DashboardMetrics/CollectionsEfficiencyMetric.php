<?php

namespace App\Reports\DashboardMetrics;

use App\Companies\Models\Member;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class CollectionsEfficiencyMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'collections_efficiency';
    }

    public function __construct(private Connection $database, ReportHelper $helper)
    {
        parent::__construct($helper);
    }

    public function build(DashboardContext $context, array $options): array
    {
        $this->setContext($context);
        $currency = $options['currency'] ?? $context->company->currency;

        return [
            'collections_efficiency' => $this->collectionsEfficiency($context, $currency),
        ];
    }

    public function invalidateCacheAfterEvent(): bool
    {
        return false;
    }

    public function getExpiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addWeek();
    }

    /**
     * Gets the ratio of invoices that have been collected on. The
     * value of the ratio is between 0 and 1.
     */
    private function collectionsEfficiency(DashboardContext $context, string $currency): float
    {
        $endDate = CarbonImmutable::now();
        $startDate = $endDate->subYear();

        $query = $this->database->createQueryBuilder()
            ->select('SUM(CASE WHEN status="paid" THEN 1 ELSE 0 END) AS paid,COUNT(*) AS total')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $context->company->id())
            ->andWhere('date BETWEEN '.$startDate->getTimestamp().' AND '.$endDate->getTimestamp())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('draft = 0')
            ->andWhere('voided = 0');

        $this->addCustomerRestriction($query, 'Invoices.customer');

        $result = (array) $query->fetchAssociative();
        $numPaid = $result['paid'];
        $numTotal = $result['total'];

        // if there are no invoices in the account then this metric is not available
        if (0 == $numTotal) {
            return -1;
        }

        return round($numPaid / $numTotal, 2);
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
