<?php

namespace App\Reports\DashboardMetrics;

use App\Companies\Models\Member;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class OpenEstimatesMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'open_estimates';
    }

    public function __construct(private Connection $database, ReportHelper $helper)
    {
        parent::__construct($helper);
    }

    public function invalidateCacheAfterEvent(): bool
    {
        return true;
    }

    public function getExpiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->endOfDay();
    }

    public function build(DashboardContext $context, array $options): array
    {
        $this->setContext($context);
        $currency = $options['currency'] ?? $this->company->currency;

        if ($context->company->features->has('estimates')) {
            $totalEstimates = $this->outstandingEstimates($currency);
        } else {
            $totalEstimates = Money::zero($currency);
        }

        return [
            'currency' => $currency,
            'total_estimates' => $totalEstimates->toDecimal(),
        ];
    }

    /**
     * Gets the total amount of outstanding estimates.
     */
    private function outstandingEstimates(string $currency): Money
    {
        $query = $this->database->createQueryBuilder()
            ->select('sum(total)')
            ->from('Estimates')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('draft = 0')
            ->andWhere('closed = 0')
            ->andWhere('voided = 0')
            ->andWhere('approved IS NULL');

        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('Estimates.customer')) {
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
        $amount = $query->fetchOne();

        return Money::fromDecimal($currency, $amount ?? 0);
    }
}
