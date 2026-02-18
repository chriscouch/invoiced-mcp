<?php

namespace App\Reports\PresetReports;

use App\Reports\ReportBuilder\ReportBuilder;
use App\Reports\ValueObjects\AgingBreakdown;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractAgingReport extends AbstractReportBuilderReport
{
    public function __construct(ReportBuilder $reportBuilder, private TranslatorInterface $translator)
    {
        parent::__construct($reportBuilder);
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }

    protected function makeAgingColumns(): array
    {
        $columns = [];
        $agingBreakdown = AgingBreakdown::fromSettings($this->company->accounts_receivable_settings);
        $dateColumn = $agingBreakdown->dateColumn;
        foreach ($agingBreakdown->getBuckets() as $bucket) {
            $columns[] = [
                'name' => $agingBreakdown->getBucketName($bucket, $this->translator, $this->company->getLocale()),
                'hide_empty' => true,
                'field' => [
                    'function' => 'age_range',
                    'arguments' => [
                        ['id' => $dateColumn],
                        ['id' => 'balance'],
                        // -1 is what the report builder expects although the aging bucket uses a null value in this case
                        $bucket['lower'] ?? -1,
                        $bucket['upper'] ?? -1,
                    ],
                ],
            ];
        }

        return $columns;
    }

    protected function withFilters(array $definition, array $parameters, ?string $saleColumn, string $customerColumn): array
    {
        if (isset($parameters['$invoiceMetadata'])) {
            foreach ($parameters['$invoiceMetadata'] as $key => $value) {
                $definition['sections'][0]['filter'][] = [
                    'field' => ['id' => ($saleColumn ? $saleColumn.'.' : '').'metadata.'.$key],
                    'operator' => '=',
                    'value' => $value,
                ];
            }
        }

        if (isset($parameters['$customerMetadata'])) {
            foreach ($parameters['$customerMetadata'] as $key => $value) {
                $definition['sections'][0]['filter'][] = [
                    'field' => ['id' => $customerColumn.'.metadata.'.$key],
                    'operator' => '=',
                    'value' => $value,
                ];
            }
        }

        return $definition;
    }
}
