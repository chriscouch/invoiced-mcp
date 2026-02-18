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

class LifetimeValue extends AbstractReport
{
    private const TABLE_HEADER = [
        ['name' => 'Month', 'type' => 'string'],
        ['name' => 'ARPU', 'type' => 'money'],
        ['name' => 'LTV', 'type' => 'money'],
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
        return 'lifetime_value';
    }

    protected function getName(): string
    {
        return 'Lifetime Value';
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
        $movementData = $this->database->fetchAllAssociative('SELECT month,movement_type,COUNT(distinct customer_id) AS num
FROM MrrMovements
WHERE tenant_id=:tenantId AND `month` BETWEEN :start AND :end
GROUP BY month, movement_type', [
            'tenantId' => $this->company->id(),
            'start' => (int) $this->startDate->format('Ym'),
            'end' => (int) $this->endDate->format('Ym'),
        ]);

        $movements = [];
        foreach ($movementData as $row) {
            $movements[$row['month'].'_'.$row['movement_type'].'_customers'] = $row['num'];
        }

        // Build up month over month values
        $date = $this->startDate;
        $tableRows = [];
        $chartData = [];
        while ($date->isBefore($this->endDate)) {
            $month = $date->format('Ym');
            $thisMonthMrr = $mrr[$month] ?? 0;
            $thisMonthCustomers = $customers[$month] ?? 0;
            $prevMonth = (int) $date->subMonth()->format('Ym');
            $previousMonthCustomers = $customers[$prevMonth] ?? 0;

            $reactivationCustomers = $movements[$month.'_'.MrrMovementType::Reactivation->value.'_customers'] ?? 0;
            $lostCustomers = $movements[$month.'_'.MrrMovementType::Lost->value.'_customers'] ?? 0;
            $lostCustomers -= $reactivationCustomers;

            // User Churn = Lost Customers Current Month / Total Customers Last Month
            $userChurn = $previousMonthCustomers > 0 ? $lostCustomers / $previousMonthCustomers : 0;

            // ARPU = MRR / Total Customers
            $arpu = $thisMonthCustomers > 0 ? $thisMonthMrr / $thisMonthCustomers : 0;

            // LTV = ARPU / User Churn; User Churn = 0 then ARPU x 36 months
            $ltv = $userChurn > 0 ? $arpu / $userChurn : $arpu * 36;

            // add the entry for the month
            $chartData[] = [
                'month' => $date->format('Y-m'),
                'ltv' => $ltv,
            ];

            $tableRows[] = [
                $date->format('F Y'),
                Money::fromDecimal($this->company->currency, $arpu),
                Money::fromDecimal($this->company->currency, $ltv),
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
                name: 'LTV',
                type: ColumnType::Money,
                alias: 'ltv',
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
        $table = $this->buildTable(self::TABLE_HEADER, $tableRows);
        $footer = $table->getFooter();
        $footer[1] = null;
        $footer[2] = null;
        $table->setFooter($footer);

        $section = new Section('');
        $section->addGroup($table);
        $this->report->addSection(
            $section
        );
    }
}
