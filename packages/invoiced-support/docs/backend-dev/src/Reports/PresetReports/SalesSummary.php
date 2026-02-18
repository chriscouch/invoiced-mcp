<?php

namespace App\Reports\PresetReports;

class SalesSummary extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'sales_summary';
    }

    protected function getDefinition(array $parameters): array
    {
        $definition = $this->getJsonDefinition('sales_summary.json');

        // Group By
        $parameters['$groupBy'] ??= 'customer';
        if (in_array($parameters['$groupBy'], ['day', 'week', 'month', 'quarter', 'year'])) {
            $fn = $parameters['$groupBy'];
            $definition['sections'][0]['fields'] = array_merge([[
                'name' => $fn,
                'field' => [
                    'function' => $fn,
                    'arguments' => [['id' => 'date']], ], ]], $definition['sections'][0]['fields']);
            $definition['sections'][0]['group'][] = [
                'field' => [
                    'function' => $fn,
                    'arguments' => [['id' => 'date']],
                ],
                'ascending' => true,
                'expanded' => false,
            ];
        } else {
            $definition['sections'][0]['fields'] = array_merge([[
                'name' => 'Customer',
                'field' => ['id' => 'customer.name'], ]], $definition['sections'][0]['fields']);
            $definition['sections'][0]['group'][] = [
                'field' => ['id' => 'customer.id'],
                'ascending' => true,
                'expanded' => false,
            ];
            $definition['sections'][0]['sort'][] = [
                'field' => ['id' => 'customer.name'],
                'ascending' => true,
            ];
        }

        // Customer Metadata filter
        if (isset($parameters['$customerMetadata'])) {
            foreach ($parameters['$customerMetadata'] as $key => $value) {
                $definition['sections'][0]['filter'][] = [
                    'field' => ['id' => 'customer.metadata.'.$key],
                    'operator' => '=',
                    'value' => $value,
                ];
            }
        }

        // Invoice Metadata filter
        if (isset($parameters['$invoiceMetadata'])) {
            foreach ($parameters['$invoiceMetadata'] as $key => $value) {
                $definition['sections'][0]['filter'][] = [
                    'field' => ['id' => 'metadata.'.$key],
                    'operator' => '=',
                    'value' => $value,
                ];
            }
        }

        return $definition;
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }
}
