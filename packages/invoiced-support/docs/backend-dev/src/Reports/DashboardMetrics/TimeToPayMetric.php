<?php

namespace App\Reports\DashboardMetrics;

use App\Companies\Models\Member;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class TimeToPayMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'time_to_pay';
    }

    public function __construct(private Connection $database, ReportHelper $helper)
    {
        parent::__construct($helper);
    }

    public function build(DashboardContext $context, array $options): array
    {
        $this->setContext($context);

        $currency = $options['currency'] ?? $context->company->currency;
        if (isset($options['end_date'])) {
            $endDate = new CarbonImmutable($options['end_date']);
        } else {
            $endDate = CarbonImmutable::now();
        }

        return [
            'average_time_to_pay' => $this->averageTimeToPay($currency, $endDate),
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
     * Gets the average time to pay. Only invoices created
     * before the supplied timestamp will be included.
     */
    private function averageTimeToPay(string $currency, CarbonImmutable $endDate): int
    {
        $startDate = $endDate->subYear();
        $query = $this->database->createQueryBuilder()
            ->select('AVG(date_paid) - AVG(date)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('status = "paid"')
            ->andWhere('currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('amount_paid > 0')
            ->andWhere('date BETWEEN '.$startDate->getTimestamp().' AND '.$endDate->getTimestamp());

        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('Invoices.customer')) {
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

        $timeToPaySeconds = $query->fetchOne();

        // if there are no paid invoices then this metric is not available
        if (null === $timeToPaySeconds) {
            return -1;
        }

        return $this->secondsToDays($timeToPaySeconds);
    }

    /**
     * Get the # of days to pay.
     */
    private function secondsToDays(int $seconds): int
    {
        return (int) round(max(0, $seconds / 86400));
    }
}
