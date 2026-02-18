<?php

namespace App\Reports\PresetReports;

class InstallmentAgingSummary extends AbstractAgingReport
{
    public static function getId(): string
    {
        return 'installment_aging_summary';
    }

    protected function getDefinition(array $parameters): array
    {
        $definition = $this->getJsonDefinition('installment_aging_summary.json');
        // Generate aging columns according to company settings
        array_splice($definition['sections'][0]['fields'], 2, 0, $this->makeAgingColumns());

        return $this->withFilters($definition, $parameters, 'invoice', 'invoice.customer');
    }

    public function makeAgingColumns(): array
    {
        $columns = parent::makeAgingColumns();
        foreach ($columns as &$column) {
            // Always use `date` column
            $column['field']['arguments'][0] = ['id' => 'date'];
            // Wrap in a SUM()
            $column['field'] = [
                'function' => 'sum',
                'arguments' => [$column['field']],
            ];
        }

        return $columns;
    }
}
