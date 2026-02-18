<?php

namespace App\Reports\DashboardMetrics;

use App\Core\I18n\ValueObjects\Money;
use App\Reports\Libs\AgingReport;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\AgingBreakdown;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class ArBalanceMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'ar_balance';
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

        // This is used to exclude future invoices from the current
        // outstanding amounts.
        $date = CarbonImmutable::now()->endOfDay();

        $aging = $this->aging($currency, $date);

        // the amount outstanding can be computed from the
        // aging buckets since they include all outstanding
        // invoices. this is a performance optimization.
        $numOpenItems = 0;
        $totalBalance = new Money($currency, 0);
        $agingResult = [];
        foreach ($aging as $row) {
            $totalBalance = $totalBalance->add($row['amount']);
            $row['amount'] = $row['amount']->toDecimal();
            $agingResult[] = $row;
            $numOpenItems += $row['count'];
        }

        $agingBreakdown = AgingBreakdown::fromSettings($this->company->accounts_receivable_settings);
        $agingDate = $agingBreakdown->dateColumn;

        return [
            'currency' => $currency,
            'total_balance' => $totalBalance->toDecimal(),
            'num_open_items' => $numOpenItems,
            'aging' => $agingResult,
            'aging_date' => $agingDate,
        ];
    }

    /**
     * Gets the aging breakdown for outstanding invoices.
     *
     * @return array [[age_lower => 0, amount => Money(), count => N], ...]
     */
    private function aging(string $currency, CarbonImmutable $date): array
    {
        $agingBreakdown = AgingBreakdown::fromSettings($this->company->accounts_receivable_settings);
        $aging = new AgingReport($agingBreakdown, $this->company, $this->database);
        $aging->setDate($date);

        if ($this->member) {
            $aging->setMember($this->member);
        }

        $agingReport = $aging->buildForCompany($currency);

        // parse the aging out of the report
        $result = [];
        $agingBuckets = $agingBreakdown->getBuckets();
        foreach ($agingBuckets as $i => $bucket) {
            $result[] = [
                'age_lower' => $bucket['lower'],
                'amount' => $agingReport[$i]['amount'],
                'count' => $agingReport[$i]['count'],
            ];
        }

        return $result;
    }
}
