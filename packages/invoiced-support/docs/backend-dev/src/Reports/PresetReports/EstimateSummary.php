<?php

namespace App\Reports\PresetReports;

class EstimateSummary extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'estimate_summary';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('estimate_summary.json');
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }
}
