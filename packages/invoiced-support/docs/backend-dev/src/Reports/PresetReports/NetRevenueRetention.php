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

class NetRevenueRetention extends AbstractReport
{
    private const TABLE_HEADER = [
        ['name' => 'Month', 'type' => 'string'],
        ['name' => 'MRR', 'type' => 'money'],
        ['name' => 'Net Revenue Retention', 'type' => 'integer'],
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
        return 'net_revenue_retention';
    }

    protected function getName(): string
    {
        return 'Net Revenue Retention';
    }

    protected function build(): void
    {
        // Get MRR in each month
        $itemData = $this->database->fetchAllAssociative('SELECT month,SUM(mrr) AS mrr
FROM MrrItems
WHERE tenant_id=:tenantId AND `month` BETWEEN :start AND :end AND partial_month=0 GROUP BY month', [
            'tenantId' => $this->company->id(),
            'start' => (int) $this->startDate->subMonth()->format('Ym'),
            'end' => (int) $this->endDate->format('Ym'),
        ]);

        $mrr = [];
        foreach ($itemData as $row) {
            $mrr[$row['month']] = $row['mrr'];
        }

        // Get MRR movements in time period
        $movementData = $this->database->fetchAllAssociative('SELECT month,movement_type,SUM(mrr) AS mrr
FROM MrrMovements
WHERE tenant_id=:tenantId AND `month` BETWEEN :start AND :end
GROUP BY month, movement_type', [
            'tenantId' => $this->company->id(),
            'start' => (int) $this->startDate->format('Ym'),
            'end' => (int) $this->endDate->format('Ym'),
        ]);

        $movements = [];
        foreach ($movementData as $row) {
            $movements[$row['month'].'_'.$row['movement_type']] = $row['mrr'];
        }

        // Build up month over month values
        $date = $this->startDate;
        $tableRows = [];
        $chartData = [];
        while ($date->isBefore($this->endDate)) {
            $month = (int) $date->format('Ym');
            $mrrCurrentMonth = $mrr[$month] ?? 0;
            $expansion = $movements[$month.'_'.MrrMovementType::Expansion->value] ?? 0;
            $reactivation = $movements[$month.'_'.MrrMovementType::Reactivation->value] ?? 0;
            $contraction = $movements[$month.'_'.MrrMovementType::Contraction->value] ?? 0;
            $lost = $movements[$month.'_'.MrrMovementType::Lost->value] ?? 0;
            $prevMonth = (int) $date->subMonth()->format('Ym');
            $mrrPreviousMonth = $mrr[$prevMonth] ?? 0;

            // Net Revenue Retention = (MRR Previous Month + Expansion MRR Current Month + Reactivation MRR Current Month - Contraction MRR Current Month + Lost MRR Current Month) / MRR Previous Month
            $nrr = 0;
            if ($mrrPreviousMonth > 0) {
                $nrr = ($mrrPreviousMonth + $expansion + $reactivation + $contraction + $lost) / $mrrPreviousMonth; // contraction and lost are already negative
            }
            $nrr = round($nrr * 100);

            // add the entry for the month
            $chartData[] = [
                'month' => $date->format('Y-m'),
                'nrr' => $nrr,
            ];

            $tableRows[] = [
                $date->format('F Y'),
                Money::fromDecimal($this->company->currency, $mrrCurrentMonth),
                number_format($nrr).'%',
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
                name: 'NRR',
                type: ColumnType::Integer,
                unit: '%',
                alias: 'nrr',
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
        $table->setFooter($footer);

        $section = new Section('');
        $section->addGroup($table);
        $this->report->addSection(
            $section
        );
    }
}
