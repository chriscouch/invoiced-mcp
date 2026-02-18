<?php

namespace App\Reports\PresetReports;

class Mrr extends AbstractMrrReport
{
    public static function getId(): string
    {
        return 'mrr';
    }

    protected function getDefinition(array $parameters): array
    {
        $definition = $this->getJsonDefinition('mrr.json');

        // MRR by Month
        $parameters['$groupBy'] ??= 'month';
        if ('month' == $parameters['$groupBy']) {
            $this->applyGroupByMonth($definition);
        }

        // MRR by Customer
        if ('customer' == $parameters['$groupBy']) {
            $this->applyGroupByCustomer($definition);
        }

        // MRR by Plan
        if ('plan' == $parameters['$groupBy']) {
            $this->applyGroupByPlan($definition);
        }

        return $definition;
    }

    private function applyGroupByMonth(array &$definition): void
    {
        // Fields
        array_splice($definition['sections'][1]['fields'], 0, 0, [[
            'name' => 'Month',
            'field' => ['id' => 'month'],
        ]]);
        array_splice($definition['sections'][1]['fields'], 2, 0, [
            [
                'name' => 'Total Subscribers',
                'subtotal' => 'none',
                'field' => [
                    'function' => 'count_distinct',
                    'arguments' => [['id' => 'customer.id']],
                ],
            ],
            [
                'name' => 'ARPU',
                'type' => 'money',
                'subtotal' => 'none',
                'field' => [
                    [
                        'function' => 'sum',
                        'arguments' => [['id' => 'mrr']],
                    ],
                    '/',
                    [
                        'function' => 'count_distinct',
                        'arguments' => [['id' => 'customer.id']],
                    ],
                ],
            ],
        ]);

        // Filter
        array_splice($definition['sections'][1]['filter'], 1, 0, [
            [
                'operator' => '>=',
                'field' => ['id' => 'month'],
                'value' => '$startMonth',
            ],
            [
                'operator' => '<=',
                'field' => ['id' => 'month'],
                'value' => '$endMonth',
            ],
        ]);

        // Group By
        $definition['sections'][1]['group'][] = [
            'field' => ['id' => 'month'],
            'ascending' => false,
            'expanded' => false,
            'fill_missing_data' => true,
        ];
    }

    private function applyGroupByCustomer(array &$definition): void
    {
        // Fields
        array_splice($definition['sections'][1]['fields'], 0, 0, [[
            'name' => 'Customer',
            'field' => ['id' => 'customer.name'],
        ]]);

        // Filter
        array_splice($definition['sections'][1]['filter'], 1, 0, [
            [
                'operator' => '=',
                'field' => ['id' => 'month'],
                'value' => '$endMonth',
            ],
        ]);

        // Group By
        $definition['sections'][1]['group'][] = [
            'field' => ['id' => 'customer.id'],
            'expanded' => false,
        ];

        // Sort by highest MRR first
        $definition['sections'][1]['sort'][] = [
            'field' => [
                'function' => 'sum',
                'arguments' => [['id' => 'mrr']],
            ],
            'ascending' => false,
        ];
    }

    private function applyGroupByPlan(array &$definition): void
    {
        // Fields
        array_splice($definition['sections'][1]['fields'], 0, 0, [[
            'name' => 'Plan',
            'field' => ['id' => 'plan.name'],
        ]]);
        array_splice($definition['sections'][1]['fields'], 2, 0, [
            [
                'name' => 'Total Subscribers',
                'subtotal' => 'none',
                'field' => [
                    'function' => 'count_distinct',
                    'arguments' => [['id' => 'customer.id']],
                ],
            ],
        ]);

        // Filter
        array_splice($definition['sections'][1]['filter'], 1, 0, [
            [
                'operator' => '=',
                'field' => ['id' => 'month'],
                'value' => '$endMonth',
            ],
        ]);

        // Group By
        $definition['sections'][1]['group'][] = [
            'field' => ['id' => 'plan.name'],
            'ascending' => true,
            'expanded' => false,
        ];

        // Sort by highest MRR first
        $definition['sections'][1]['sort'][] = [
            'field' => [
                'function' => 'sum',
                'arguments' => [['id' => 'mrr']],
            ],
            'ascending' => false,
        ];
    }
}
