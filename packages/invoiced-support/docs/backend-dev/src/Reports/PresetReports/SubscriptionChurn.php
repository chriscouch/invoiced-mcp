<?php

namespace App\Reports\PresetReports;

use App\Core\I18n\ValueObjects\Money;
use App\Reports\Enums\ColumnType;
use App\Reports\Libs\ChartBuilder;
use App\Reports\Libs\ReportHelper;
use App\Reports\ReportBuilder\ValueObjects\ConstantExpression;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ValueObjects\Section;
use App\SubscriptionBilling\Enums\MrrMovementType;
use Doctrine\DBAL\Connection;

class SubscriptionChurn extends AbstractReport
{
    private const TABLE_HEADER = [
        ['name' => 'Month', 'type' => 'string'],
        ['name' => 'Lost Users', 'type' => 'integer'],
        ['name' => 'User Churn', 'type' => 'float'],
        ['name' => 'Lost MRR', 'type' => 'money'],
        ['name' => 'Revenue Churn', 'type' => 'float'],
    ];

    public function __construct(
        Connection $database,
        ReportHelper $helper,
        private ChartBuilder $chartBuilder,
    ) {
        parent::__construct($database, $helper);
    }

    public static function getId(): string
    {
        return 'subscription_churn';
    }

    protected function getName(): string
    {
        return 'Subscription Churn';
    }

    protected function build(): void
    {
        // Get customers and MRR in each month
        $itemData = $this->database->fetchAllAssociative('SELECT month,SUM(mrr) AS mrr,COUNT(distinct customer_id) AS num
FROM MrrItems
WHERE tenant_id=:tenantId AND `month` BETWEEN :start AND :end AND partial_month=0 GROUP BY month', [
            'tenantId' => $this->company->id(),
            'start' => (int) $this->startDate->subMonth()->format('Ym'),
            'end' => (int) $this->endDate->format('Ym'),
        ]);

        $mrr = [];
        $customers = [];
        foreach ($itemData as $row) {
            $mrr[$row['month']] = $row['mrr'];
            $customers[$row['month']] = $row['num'];
        }

        // Get MRR movements in time period
        $movementData = $this->database->fetchAllAssociative('SELECT month,movement_type,SUM(mrr) AS mrr,COUNT(distinct customer_id) AS num
FROM MrrMovements
WHERE tenant_id=:tenantId AND `month` BETWEEN :start AND :end
GROUP BY month, movement_type', [
            'tenantId' => $this->company->id(),
            'start' => (int) $this->startDate->format('Ym'),
            'end' => (int) $this->endDate->format('Ym'),
        ]);

        $movements = [];
        foreach ($movementData as $row) {
            $movements[$row['month'].'_'.$row['movement_type'].'_mrr'] = $row['mrr'];
            $movements[$row['month'].'_'.$row['movement_type'].'_customers'] = $row['num'];
        }

        // Build up month over month values
        $date = $this->startDate;
        $tableRows = [];
        $chartData = [];
        while ($date->isBefore($this->endDate)) {
            $month = $date->format('Ym');
            $prevMonth = (int) $date->subMonth()->format('Ym');
            $previousMonthCustomers = $customers[$prevMonth] ?? 0;
            $previousMonthMrr = $mrr[$prevMonth] ?? 0;

            $reactivationCustomers = $movements[$month.'_'.MrrMovementType::Reactivation->value.'_customers'] ?? 0;
            $lostCustomers = $movements[$month.'_'.MrrMovementType::Lost->value.'_customers'] ?? 0;
            $lostCustomers -= $reactivationCustomers;

            $lostMrr = $movements[$month.'_'.MrrMovementType::Lost->value.'_mrr'] ?? 0;
            $reactivationMrr = $movements[$month.'_'.MrrMovementType::Reactivation->value.'_mrr'] ?? 0;
            $lostMrr += $reactivationMrr;
            $lostMrr *= -1;

            // User Churn = Lost Customers Current Month / Total Customers Last Month
            $userChurn = $previousMonthCustomers > 0 ? $lostCustomers / $previousMonthCustomers : 0;
            $userChurn = round($userChurn * 10000) / 100;

            // Revenue Churn = Lost MRR Current Month / MRR Previous Month
            $revenueChurn = $previousMonthMrr > 0 ? $lostMrr / $previousMonthMrr : 0;
            $revenueChurn = round($revenueChurn * 10000) / 100;

            // add the entry for the month
            $chartData[] = [
                'month' => $date->format('Y-m'),
                'user_churn' => $userChurn,
                'revenue_churn' => $revenueChurn,
            ];

            $tableRows[] = [
                $date->format('F Y'),
                $lostCustomers,
                number_format($userChurn, 2).'%',
                Money::fromDecimal($this->company->currency, $lostMrr),
                number_format($revenueChurn, 2).'%',
            ];

            $date = $date->addMonth();
        }

        // Build the chart
        $fields = [
            new SelectColumn(
                expression: new ConstantExpression('1', true), // not used
                name: 'Month',
                type: ColumnType::Month,
                alias: 'month'
            ),
            new SelectColumn(
                expression: new ConstantExpression('1', true), // not used
                name: 'User Churn',
                type: ColumnType::Integer,
                unit: '%',
                alias: 'user_churn',
            ),
            new SelectColumn(
                expression: new ConstantExpression('1', true), // not used
                name: 'Revenue Churn',
                type: ColumnType::Integer,
                unit: '%',
                alias: 'revenue_churn',
            ),
        ];

        $chart = $this->chartBuilder->makeLineChart($this->company, $fields, $chartData, $this->parameters, []);
        $section = new Section('');
        $section->addGroup($chart);
        $this->report->addSection(
            $section
        );

        // Build the table
        $tableRows = array_reverse($tableRows); // sort descending order
        $this->report->addSection(
            $this->buildTableSection('', self::TABLE_HEADER, $tableRows)
        );
    }
}
