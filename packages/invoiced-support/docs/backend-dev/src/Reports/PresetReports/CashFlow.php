<?php

namespace App\Reports\PresetReports;

use App\Core\I18n\ValueObjects\Money;
use App\Reports\ValueObjects\ChartGroup;
use App\Reports\ValueObjects\Section;
use Carbon\CarbonImmutable;

class CashFlow extends AbstractReport
{
    public static function getId(): string
    {
        return 'cash_flow';
    }

    private string $locale;

    protected function getName(): string
    {
        return 'Cash Flow Forecast';
    }

    protected function build(): void
    {
        $this->locale = $this->company->getLocale();
        /** @var CarbonImmutable $start */
        $start = CarbonImmutable::createFromTimestamp($this->start)->locale($this->locale);
        /** @var CarbonImmutable $end */
        $end = CarbonImmutable::createFromTimestamp($this->end)->locale($this->locale);
        $forecast = $this->makeForecast($this->currency, $start, $end);

        $chartData = [
            'datasets' => [
                [
                    'label' => 'Promise-to-Pays',
                    'data' => [],
                    'backgroundColor' => '#003f5c',
                ],
                [
                    'label' => 'AutoPay',
                    'data' => [],
                    'backgroundColor' => '#bc5090',
                ],
                [
                    'label' => 'Installments',
                    'data' => [],
                    'backgroundColor' => '#ffa600',
                ],
                [
                    'label' => 'Due Date',
                    'data' => [],
                    'backgroundColor' => '#ff6361',
                ],
            ],
            'labels' => [],
        ];
        $weeklyRows = [];
        foreach ($forecast['weekly'] as $row) {
            $weeklyRows[] = $this->buildTableRow($row['start_date'].' - '.$row['end_date'], $row);
            $chartData['labels'][] = $row['start_date'];
            $chartData['datasets'][0]['data'][] = $row['total_promise_to_pay']->toDecimal();
            $chartData['datasets'][1]['data'][] = $row['total_autopay']->toDecimal();
            $chartData['datasets'][2]['data'][] = $row['total_installments']->toDecimal();
            $chartData['datasets'][3]['data'][] = $row['total_due_soon']->toDecimal();
        }

        // Chart
        $chart = new ChartGroup();
        $chart->setChartType('bar');
        $chart->setData($chartData);
        $chart->setChartOptions([
            'scales' => [
                'xAxes' => [
                    [
                        'stacked' => true,
                        'gridLines' => [
                            'display' => false,
                        ],
                    ],
                ],
                'yAxes' => [
                    [
                        'stacked' => true,
                        'ticks' => [
                            'suggestedMin' => 0,
                            'type' => 'money',
                            'currency' => $this->currency,
                        ],
                        'gridLines' => [
                            'color' => '#EAEDEC',
                        ],
                    ],
                ],
            ],
            'tooltips' => [
                'type' => 'money',
                'currency' => $this->currency,
            ],
        ]);
        $this->report->addSection((new Section(''))->addGroup($chart));

        // Weekly Table
        $header = [
            ['name' => 'Week', 'type' => 'string'],
            ['name' => 'Promise-to-Pays', 'type' => 'money'],
            ['name' => 'AutoPay', 'type' => 'money'],
            ['name' => 'Installments', 'type' => 'money'],
            ['name' => 'Due Date', 'type' => 'money'],
            ['name' => 'Total', 'type' => 'money'],
        ];
        $this->report->addSection(
            $this->buildTableSection('Weekly', $header, $weeklyRows)
        );

        // Monthly Table
        $header[0]['name'] = 'Month';
        $monthlyRows = [];
        foreach ($forecast['monthly'] as $row) {
            $monthlyRows[] = $this->buildTableRow($row['month'], $row);
        }
        $this->report->addSection(
            $this->buildTableSection('Monthly', $header, $monthlyRows)
        );
    }

    public function totalPromiseToPays(CarbonImmutable $startDate, CarbonImmutable $endDate, string $currency): array
    {
        $sql = 'SELECT DATE_FORMAT(FROM_UNIXTIME(ExpectedPaymentDates.date),"%Y-%m-%d") AS `day`,SUM(balance) AS amount ';
        $sql .= 'FROM Invoices JOIN ExpectedPaymentDates ON invoice_id=Invoices.id ';
        $sql .= 'WHERE Invoices.tenant_id=? AND closed=0 AND draft=0 AND voided=0 AND paid=0 AND Invoices.currency=? ';
        $sql .= 'AND payment_plan_id IS NULL AND autopay=0 ';
        $sql .= 'AND ExpectedPaymentDates.date BETWEEN ? AND ? ';
        $sql .= 'GROUP BY `day` ORDER BY `day` ASC';

        $result = $this->database->fetchAllAssociative($sql, [$this->company->id(), $currency, $startDate->getTimestamp(), $endDate->getTimestamp()]);
        foreach ($result as &$row) {
            $row['timestamp'] = (new CarbonImmutable($row['day']))->setTime(0, 0)->locale($this->locale);
        }

        return $result;
    }

    public function totalAutoPay(CarbonImmutable $startDate, CarbonImmutable $endDate, string $currency): array
    {
        $sql = 'SELECT DATE_FORMAT(FROM_UNIXTIME(next_payment_attempt),"%Y-%m-%d") AS `day`,SUM(balance) AS amount ';
        $sql .= 'FROM Invoices ';
        $sql .= 'WHERE tenant_id=? AND closed=0 AND draft=0 AND paid=0 AND voided=0 AND currency=? ';
        $sql .= 'AND payment_plan_id IS NULL AND autopay=1 AND next_payment_attempt BETWEEN ? AND ? ';
        $sql .= 'GROUP BY `day` ORDER BY `day` ASC';

        $result = $this->database->fetchAllAssociative($sql, [$this->company->id(), $currency, $startDate->getTimestamp(), $endDate->getTimestamp()]);
        foreach ($result as &$row) {
            $row['timestamp'] = (new CarbonImmutable($row['day']))->setTime(0, 0)->locale($this->locale);
        }

        return $result;
    }

    public function totalInstallments(CarbonImmutable $startDate, CarbonImmutable $endDate, string $currency): array
    {
        $sql = 'SELECT DATE_FORMAT(FROM_UNIXTIME(PaymentPlanInstallments.date),"%Y-%m-%d") AS `day`,SUM(PaymentPlanInstallments.balance) AS amount ';
        $sql .= 'FROM PaymentPlanInstallments JOIN Invoices ON Invoices.payment_plan_id=PaymentPlanInstallments.payment_plan_id ';
        $sql .= 'WHERE PaymentPlanInstallments.tenant_id=? AND closed=0 AND draft=0 AND paid=0 AND voided=0 AND currency=? ';
        $sql .= 'AND PaymentPlanInstallments.date BETWEEN ? AND ? ';
        $sql .= 'GROUP BY `day` ORDER BY `day` ASC';

        $result = $this->database->fetchAllAssociative($sql, [$this->company->id(), $currency, $startDate->getTimestamp(), $endDate->getTimestamp()]);
        foreach ($result as &$row) {
            $row['timestamp'] = (new CarbonImmutable($row['day']))->setTime(0, 0)->locale($this->locale);
        }

        return $result;
    }

    public function totalFutureDue(CarbonImmutable $startDate, CarbonImmutable $endDate, string $currency): array
    {
        $sql = 'SELECT DATE_FORMAT(FROM_UNIXTIME(due_date),"%Y-%m-%d") AS `day`,SUM(balance) AS amount ';
        $sql .= 'FROM Invoices ';
        $sql .= 'WHERE tenant_id=? AND closed=0 AND draft=0 AND voided=0 AND paid=0 AND currency=? ';
        $sql .= 'AND payment_plan_id IS NULL AND autopay=0 AND NOT EXISTS (SELECT 1 FROM ExpectedPaymentDates WHERE invoice_id=Invoices.id) ';
        $sql .= 'AND due_date BETWEEN ? AND ? ';
        $sql .= 'GROUP BY `day` ORDER BY `day` ASC';

        $result = $this->database->fetchAllAssociative($sql, [$this->company->id(), $currency, $startDate->getTimestamp(), $endDate->getTimestamp()]);
        foreach ($result as &$row) {
            $row['timestamp'] = (new CarbonImmutable($row['day']))->setTime(0, 0)->locale($this->locale);
        }

        return $result;
    }

    public static function isWithinWeek(CarbonImmutable $timestamp, CarbonImmutable $weekStart): bool
    {
        // Cannot use isSameWeek() because it always assumes the week starts on Monday
        // which is not true in every locale.
        $weekEnd = $weekStart->endOfWeek();

        return $timestamp->greaterThanOrEqualTo($weekStart) && $timestamp->lessThanOrEqualTo($weekEnd);
    }

    public static function isWithinMonth(CarbonImmutable $timestamp, CarbonImmutable $monthStart): bool
    {
        return $timestamp->isSameYear($monthStart) && $timestamp->isSameMonth($monthStart);
    }

    /**
     * Build forecast generation output.
     */
    public function makeForecast(string $currency, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $weeklyForecast = [];
        $monthlyForecast = [];

        $weekStart = $startDate->startOfWeek();
        while ($weekStart->lessThan($endDate)) {
            $weeklyForecast[] = [
                'start_date' => $weekStart->max($startDate)->format($this->dateFormat),
                'end_date' => $weekStart->endOfWeek()->min($endDate)->format($this->dateFormat),
                'start' => $weekStart,
                'total_promise_to_pay' => new Money($currency, 0),
                'total_installments' => new Money($currency, 0),
                'total_autopay' => new Money($currency, 0),
                'total_due_soon' => new Money($currency, 0),
                'total' => new Money($currency, 0),
            ];
            $weekStart = $weekStart->addWeek()->startOfWeek();
        }

        $monthStart = $startDate->startOfMonth();
        while ($monthStart->lessThan($endDate)) {
            $monthlyForecast[] = [
                'month' => $monthStart->format('F Y'),
                'start' => $monthStart,
                'num_weeks' => 0,
                'total_promise_to_pay' => new Money($currency, 0),
                'total_installments' => new Money($currency, 0),
                'total_autopay' => new Money($currency, 0),
                'total_due_soon' => new Money($currency, 0),
                'total' => new Money($currency, 0),
            ];

            $monthStart = $monthStart->addMonth()->startOfMonth();
        }

        // add in forecast results
        $promiseToPay = $this->totalPromiseToPays($startDate, $endDate, $currency);
        $this->addInData($promiseToPay, $weeklyForecast, $monthlyForecast, 'total_promise_to_pay');

        $autopay = $this->totalAutoPay($startDate, $endDate, $currency);
        $this->addInData($autopay, $weeklyForecast, $monthlyForecast, 'total_autopay');

        $installments = $this->totalInstallments($startDate, $endDate, $currency);
        $this->addInData($installments, $weeklyForecast, $monthlyForecast, 'total_installments');

        $futureDue = $this->totalFutureDue($startDate, $endDate, $currency);
        $this->addInData($futureDue, $weeklyForecast, $monthlyForecast, 'total_due_soon');

        // calculate the number of weeks in each month
        $currMonth = false;
        $currMonthIndex = -1;
        foreach ($weeklyForecast as $row) {
            $month = $row['start']->format('n');
            if (!$currMonth || $currMonth != $month) {
                $currMonth = $month;
                ++$currMonthIndex;
            }
            // The months from the weeks don't necessarily line up with the months generated
            if (isset($monthlyForecast[$currMonthIndex])) {
                ++$monthlyForecast[$currMonthIndex]['num_weeks'];
            }
        }

        return [
            'weekly' => $weeklyForecast,
            'monthly' => $monthlyForecast,
        ];
    }

    private function addInData(array $futureDue, array &$weeklyResult, array &$monthlyResult, string $key): void
    {
        $weekIndex = 0;
        $monthIndex = 0;
        foreach ($futureDue as $row) {
            // move further up the week list
            // until we find a matching week
            while (!self::isWithinWeek($row['timestamp'], $weeklyResult[$weekIndex]['start']) && $weekIndex < count($weeklyResult) - 1) {
                ++$weekIndex;
            }

            $amount = Money::fromDecimal($weeklyResult[$weekIndex][$key]->currency, $row['amount']);
            $weeklyResult[$weekIndex][$key] = $weeklyResult[$weekIndex][$key]->add($amount);
            $weeklyResult[$weekIndex]['total'] = $weeklyResult[$weekIndex]['total']->add($amount);

            // move further up the month list
            // until we find a matching month
            while (!self::isWithinMonth($row['timestamp'], $monthlyResult[$monthIndex]['start']) && $monthIndex < count($monthlyResult) - 1) {
                ++$monthIndex;
            }

            $monthlyResult[$monthIndex][$key] = $monthlyResult[$monthIndex][$key]->add($amount);
            $monthlyResult[$monthIndex]['total'] = $monthlyResult[$monthIndex]['total']->add($amount);
        }
    }

    private function buildTableRow(string $label, array $row): array
    {
        return [
            $label,
            !$row['total_promise_to_pay']->isZero() ? $row['total_promise_to_pay'] : null,
            !$row['total_autopay']->isZero() ? $row['total_autopay'] : null,
            !$row['total_installments']->isZero() ? $row['total_installments'] : null,
            !$row['total_due_soon']->isZero() ? $row['total_due_soon'] : null,
            !$row['total']->isZero() ? $row['total'] : null,
        ];
    }
}
