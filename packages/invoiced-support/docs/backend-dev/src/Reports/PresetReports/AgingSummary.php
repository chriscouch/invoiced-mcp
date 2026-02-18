<?php

namespace App\Reports\PresetReports;

class AgingSummary extends AbstractAgingReport
{
    public static function getId(): string
    {
        return 'aging_summary';
    }

    protected function getDefinition(array $parameters): array
    {
        $definition = $this->getJsonDefinition('aging_summary.json');
        // Generate aging columns according to company settings
        array_splice($definition['sections'][0]['fields'], 2, 0, $this->makeAgingColumns());

        return $this->withFilters($definition, $parameters, null, 'customer');
    }

    public function makeAgingColumns(): array
    {
        $columns = parent::makeAgingColumns();
        foreach ($columns as &$column) {
            // Wrap in a SUM()
            $column['field'] = [
                'function' => 'sum',
                'arguments' => [$column['field']],
            ];
        }

        return $columns;
    }
}
