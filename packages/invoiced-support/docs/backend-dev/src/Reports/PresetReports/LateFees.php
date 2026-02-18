<?php

namespace App\Reports\PresetReports;

class LateFees extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'late_fees';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('late_fees.json');
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }
}
