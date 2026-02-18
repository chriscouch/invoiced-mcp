<?php

namespace App\Reports\PresetReports;

class InstallmentAgingDetail extends AbstractAgingReport
{
    public static function getId(): string
    {
        return 'installment_aging_detail';
    }

    protected function getDefinition(array $parameters): array
    {
        $definition = $this->getJsonDefinition('installment_aging_detail.json');
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
        }

        return $columns;
    }
}
