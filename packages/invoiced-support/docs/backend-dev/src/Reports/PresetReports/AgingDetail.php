<?php

namespace App\Reports\PresetReports;

class AgingDetail extends AbstractAgingReport
{
    public static function getId(): string
    {
        return 'aging_detail';
    }

    protected function getDefinition(array $parameters): array
    {
        $definition = $this->getJsonDefinition('aging_detail.json');
        // Generate aging columns according to company settings
        array_splice($definition['sections'][0]['fields'], 2, 0, $this->makeAgingColumns());

        return $this->withFilters($definition, $parameters, null, 'customer');
    }
}
