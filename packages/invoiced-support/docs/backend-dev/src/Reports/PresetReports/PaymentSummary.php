<?php

namespace App\Reports\PresetReports;

class PaymentSummary extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'payment_summary';
    }

    protected function getDefinition(array $parameters): array
    {
        $definition = $this->getJsonDefinition('payment_summary.json');

        // Group By
        $parameters['$groupBy'] ??= 'method';
        if (in_array($parameters['$groupBy'], ['day', 'week', 'month', 'quarter', 'year'])) {
            $fn = $parameters['$groupBy'];
            $definition['sections'][0]['fields'] = array_merge([[
                'name' => $fn,
                'field' => [
                    'function' => $fn,
                    'arguments' => [['id' => 'date']],
                ],
            ]], $definition['sections'][0]['fields']);
            $definition['sections'][0]['group'][] = [
                'field' => [
                    'function' => $fn,
                    'arguments' => [['id' => 'date']],
                ],
                'ascending' => true,
                'expanded' => false,
            ];

            $definition['sections'][1]['fields'] = array_merge([[
                'name' => $fn,
                'field' => [
                    'function' => $fn,
                    'arguments' => [['id' => 'date']],
                ],
            ]], $definition['sections'][1]['fields']);
            $definition['sections'][1]['group'][] = [
                'field' => [
                    'function' => $fn,
                    'arguments' => [['id' => 'date']],
                ],
                'ascending' => true,
                'expanded' => false,
            ];
        } elseif ('customer' == $parameters['$groupBy']) {
            $definition['sections'][0]['fields'] = array_merge([[
                'name' => 'Customer',
                'field' => ['id' => 'customer.name'],
            ]], $definition['sections'][0]['fields']);
            $definition['sections'][0]['group'][] = [
                'field' => ['id' => 'customer.id'],
                'ascending' => true,
                'expanded' => false,
            ];
            $definition['sections'][0]['sort'][] = [
                'field' => ['id' => 'customer.name'],
                'ascending' => true,
            ];

            $definition['sections'][1]['fields'] = array_merge([[
                'name' => 'Customer',
                'field' => ['id' => 'customer.name'],
            ]], $definition['sections'][1]['fields']);
            $definition['sections'][1]['group'][] = [
                'field' => ['id' => 'customer.id'],
                'ascending' => true,
                'expanded' => false,
            ];
            $definition['sections'][1]['sort'][] = [
                'field' => ['id' => 'customer.name'],
                'ascending' => true,
            ];
        } else {
            $definition['sections'][0]['fields'] = array_merge([[
                'name' => 'Payment Method',
                'field' => ['id' => 'method'],
            ]], $definition['sections'][0]['fields']);
            $definition['sections'][0]['group'][] = [
                'field' => ['id' => 'method'],
                'ascending' => true,
                'expanded' => false,
            ];

            $definition['sections'][1]['fields'] = array_merge([[
                'name' => 'Payment Method',
                'field' => ['id' => 'method'],
            ]], $definition['sections'][1]['fields']);
            $definition['sections'][1]['group'][] = [
                'field' => ['id' => 'method'],
                'ascending' => true,
                'expanded' => false,
            ];
        }

        // Customer Metadata filter
        if (isset($parameters['$customerMetadata'])) {
            foreach ($parameters['$customerMetadata'] as $key => $value) {
                $filter = [
                    'field' => ['id' => 'customer.metadata.'.$key],
                    'operator' => '=',
                    'value' => $value,
                ];
                $definition['sections'][0]['filter'][] = $filter;
                $definition['sections'][1]['filter'][] = $filter;
            }
        }

        // Invoice Metadata filter
        if (isset($parameters['$invoiceMetadata'])) {
            // When invoice metadata filter is used then the
            // payments section should not be displayed since
            // the filter is not supported there.
            unset($definition['sections'][0]);

            foreach ($parameters['$invoiceMetadata'] as $key => $value) {
                $definition['sections'][1]['filter'][] = [
                    'field' => ['id' => 'invoice.metadata.'.$key],
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
