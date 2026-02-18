<?php

namespace App\Reports\PresetReports;

use App\Reports\Enums\ColumnType;
use App\Reports\Libs\ChartBuilder;
use App\Reports\Libs\ReportHelper;
use App\Reports\ReportBuilder\ValueObjects\ConstantExpression;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ValueObjects\Section;
use App\SubscriptionBilling\Enums\MrrMovementType;
use Doctrine\DBAL\Connection;

class TotalSubscribers extends AbstractReport
{
    private const TABLE_HEADER = [
        ['name' => 'Month', 'type' => 'string'],
        ['name' => 'New Business', 'type' => 'integer'],
        ['name' => 'Reactivation', 'type' => 'integer'],
        ['name' => 'Lost', 'type' => 'integer'],
        ['name' => 'Total Subscribers', 'type' => 'integer'],
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
        return 'total_subscribers';
    }

    protected function getName(): string
    {
        return 'Total Subscribers';
    }

    protected function build(): void
    {
        // Get starting customers
        $startingCustomers = (int) $this->database->fetchOne('SELECT COUNT(distinct customer_id)
FROM MrrItems
WHERE tenant_id=:tenantId AND `month` = :month AND partial_month=0', [
            'tenantId' => $this->company->id(),
            'month' => (int) $this->startDate->subMonth()->format('Ym'),
        ]);

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
            $movements[$row['month'].'_'.$row['movement_type']] = $row['num'];
        }

        // Build up month over month values
        $runningTotal = $startingCustomers;
        $date = $this->startDate;
        $tableRows = [];
        $chartData = [];
        while ($date->isBefore($this->endDate)) {
            $month = $date->format('Ym');
            $newBusiness = $movements[$month.'_'.MrrMovementType::NewBusiness->value] ?? 0;
            $reactivation = $movements[$month.'_'.MrrMovementType::Reactivation->value] ?? 0;
            $lost = $movements[$month.'_'.MrrMovementType::Lost->value] ?? 0;

            // Total Subscribers = Starting Subscribers + New Business + Reactivations - Lost
            $runningTotal += $newBusiness + $reactivation - $lost;

            // add the entry for the month
            $chartData[] = [
                'month' => $date->format('Y-m'),
                'subscribers' => $runningTotal,
            ];

            $tableRows[] = [
                $date->format('F Y'),
                $newBusiness,
                $reactivation,
                -$lost,
                $runningTotal,
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
                name: 'Subscribers',
                type: ColumnType::Integer,
                alias: 'subscribers',
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
        $footer[4] = null;
        $table->setFooter($footer);

        $section = new Section('');
        $section->addGroup($table);
        $this->report->addSection(
            $section
        );
    }
}
