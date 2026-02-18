<?php

namespace App\Reports\PresetReports;

class MrrMovements extends AbstractMrrReport
{
    public static function getId(): string
    {
        return 'mrr_movements';
    }

    protected function getDefinition(array $parameters): array
    {
        $definition = $this->getJsonDefinition('mrr_movements.json');

        // Movements by Month
        $parameters['$groupBy'] ??= 'month';
        if ('month' == $parameters['$groupBy']) {
            $this->applyGroupByMonth($definition);
        }

        // Movements by Customer
        if ('customer' == $parameters['$groupBy']) {
            $this->applyGroupByCustomer($definition);
        }

        // Movements by Plan
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
        unset($definition['sections'][1]['fields'][2]);
        unset($definition['sections'][1]['fields'][4]);
        unset($definition['sections'][1]['fields'][6]);
        unset($definition['sections'][1]['fields'][8]);
        unset($definition['sections'][1]['fields'][10]);
        unset($definition['sections'][1]['fields'][12]);

        // Group By
        $definition['sections'][1]['group'][] = [
            'field' => ['id' => 'customer.id'],
            'expanded' => false,
        ];

        // Sort by highest net MRR movement first
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

        // Group By
        $definition['sections'][1]['group'][] = [
            'field' => ['id' => 'plan.name'],
            'ascending' => true,
            'expanded' => false,
        ];

        // Sort by highest net MRR movement first
        $definition['sections'][1]['sort'][] = [
            'field' => [
                'function' => 'sum',
                'arguments' => [['id' => 'mrr']],
            ],
            'ascending' => false,
        ];
    }
}
