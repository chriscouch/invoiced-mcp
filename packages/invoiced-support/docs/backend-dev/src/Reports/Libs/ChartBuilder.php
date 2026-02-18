<?php

namespace App\Reports\Libs;

use App\Companies\Models\Company;
use App\Reports\ReportBuilder\Formatter\ValueFormatter;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ValueObjects\ChartGroup;

class ChartBuilder
{
    private const CHART_COLORS = [
        '#10806F',
        '#B4D9BD',
        '#e66b55',
        '#ffbf3e',
        '#4b94d9',
        '#00529B',
        '#7AC142',
        '#F54F52',
        '#9552EA',
        '#FDBB2F',
        '#F47A1F',
        '#007CC3',
        '#377B2B',
    ];

    /**
     * @param SelectColumn[] $fields
     */
    public function makeBarChart(Company $company, array $fields, array $data, array $parameters, array $chartOptions): ChartGroup
    {
        $valueFormatter = ValueFormatter::forCompany($company);
        $labels = [];
        $datasets = [];

        $colors = $chartOptions['colors'] ?? null;
        $tooltipType = null;
        foreach ($fields as $i => $field) {
            $i = (int) $i;
            if ($i > 0) {
                $datasets[] = [
                    'label' => $field->name,
                    'data' => [],
                    'backgroundColor' => $this->getDatasetColor($i - 1, $colors),
                ];
                $tooltipType = $field->type;
            }

            foreach ($data as $dataRow) {
                $value = $dataRow[$field->alias];
                if (0 == $i) {
                    $labels[] = $this->formatStringValue($value, $company, $field, $valueFormatter);
                } else {
                    $datasets[$i - 1]['data'][] = (float) $value;
                }
            }
        }

        $group = new ChartGroup();
        $group->setChartType('bar');
        $group->setData([
            'datasets' => $datasets,
            'labels' => $labels,
        ]);

        $group->setChartOptions([
            'scales' => [
                'xAxes' => [
                    [
                        'stacked' => $chartOptions['stacked'] ?? false,
                        'gridLines' => [
                            'display' => false,
                        ],
                    ],
                ],
                'yAxes' => [
                    [
                        'stacked' => $chartOptions['stacked'] ?? false,
                        'ticks' => [
                            'suggestedMin' => 0,
                            'type' => $tooltipType,
                            'currency' => $parameters['$currency'] ?? $company->currency,
                        ],
                        'gridLines' => [
                            'color' => '#EAEDEC',
                        ],
                    ],
                ],
            ],
            'tooltips' => [
                'type' => $tooltipType,
                'currency' => $parameters['$currency'] ?? $company->currency,
            ],
        ]);

        return $group;
    }

    /**
     * @param SelectColumn[] $fields
     */
    public function makeLineChart(Company $company, array $fields, array $data, array $parameters, array $chartOptions): ChartGroup
    {
        $valueFormatter = ValueFormatter::forCompany($company);
        $labels = [];
        $datasets = [];

        $colors = $chartOptions['colors'] ?? null;
        $tooltipType = null;
        foreach ($fields as $i => $field) {
            $i = (int) $i;
            if ($i > 0) {
                $color = $this->getDatasetColor($i - 1, $colors);
                $datasets[] = [
                    'label' => $field->name,
                    'data' => [],
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'fill' => false,
                ];
                $tooltipType = $field->type;
            }

            foreach ($data as $dataRow) {
                $value = $dataRow[$field->alias];
                if (0 == $i) {
                    $labels[] = $this->formatStringValue($value, $company, $field, $valueFormatter);
                } else {
                    $datasets[$i - 1]['data'][] = (float) $value;
                }
            }
        }

        $group = new ChartGroup();
        $group->setChartType('line');
        $group->setData([
            'datasets' => $datasets,
            'labels' => $labels,
        ]);

        $group->setChartOptions([
            'scales' => [
                'xAxes' => [
                    [
                        'gridLines' => [
                            'display' => false,
                        ],
                    ],
                ],
                'yAxes' => [
                    [
                        'ticks' => [
                            'suggestedMin' => 0,
                            'type' => $tooltipType,
                            'currency' => $parameters['$currency'] ?? $company->currency,
                        ],
                        'gridLines' => [
                            'color' => '#EAEDEC',
                        ],
                    ],
                ],
            ],
            'tooltips' => [
                'type' => $tooltipType,
                'currency' => $parameters['$currency'] ?? $company->currency,
            ],
        ]);

        return $group;
    }

    /**
     * @param SelectColumn[] $fields
     */
    public function makeRadarChart(Company $company, array $fields, array $data, array $chartOptions): ChartGroup
    {
        $valueFormatter = ValueFormatter::forCompany($company);
        $labels = [];
        $datasets = [];

        $colors = $chartOptions['colors'] ?? null;
        foreach ($fields as $i => $field) {
            $i = (int) $i;
            if ($i > 0) {
                $datasets[] = [
                    'label' => $field->name,
                    'data' => [],
                    'backgroundColor' => $this->getDatasetColor($i - 1, $colors),
                ];
            }

            foreach ($data as $dataRow) {
                $value = $dataRow[$field->alias];
                if (0 == $i) {
                    $labels[] = $this->formatStringValue($value, $company, $field, $valueFormatter);
                } else {
                    $datasets[$i - 1]['data'][] = (float) $value;
                }
            }
        }

        $group = new ChartGroup();
        $group->setChartType('radar');
        $group->setData([
            'datasets' => $datasets,
            'labels' => $labels,
        ]);

        return $group;
    }

    /**
     * @param SelectColumn[] $fields
     */
    public function makePieChart(Company $company, array $fields, array $data, array $parameters, array $chartOptions, string $type): ChartGroup
    {
        $valueFormatter = ValueFormatter::forCompany($company);
        $labels = [];
        $datasets = [];

        if (isset($chartOptions['colors'])) {
            $colors = $chartOptions['colors'];
        } else {
            $colors = [];
            for ($i = 0; $i < count($data); ++$i) {
                $colors[] = $this->getDatasetColor($i);
            }
        }

        $tooltipType = null;
        foreach ($fields as $i => $field) {
            $i = (int) $i;
            if ($i > 0) {
                $datasets[] = [
                    'label' => $field->name,
                    'data' => [],
                    'backgroundColor' => $colors,
                ];
                $tooltipType = $field->type;
            }

            foreach ($data as $dataRow) {
                $value = $dataRow[$field->alias];
                if (0 == $i) {
                    $labels[] = $this->formatStringValue($value, $company, $field, $valueFormatter);
                } else {
                    $datasets[$i - 1]['data'][] = (float) $value;
                }
            }
        }

        $group = new ChartGroup();
        $group->setChartType($type);
        $group->setData([
            'datasets' => $datasets,
            'labels' => $labels,
        ]);
        $group->setChartOptions([
            'tooltips' => [
                'type' => $tooltipType,
                'currency' => $parameters['$currency'] ?? '',
            ],
        ]);

        return $group;
    }

    private function getDatasetColor(int $i, ?array $colors = null): string
    {
        $colors ??= self::CHART_COLORS;

        if (!count($colors)) {
            $colors = self::CHART_COLORS;
        }

        return $colors[$i] ?? $colors[random_int(0, count($colors) - 1)];
    }

    private function formatStringValue(mixed $value, Company $company, SelectColumn $field, ValueFormatter $valueFormatter): string
    {
        // The parameters argument is not used because it is only
        // needed for currency formatting which will not happen here.
        $value = $valueFormatter->format($company, $field, $value, []);
        if (is_array($value)) {
            $value = $value['formatted'] ?? null;
        } elseif (!is_string($value)) {
            $value = (string) $value;
        }

        return $value ?: 'Empty';
    }
}
