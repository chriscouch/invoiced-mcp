<?php

namespace App\Reports\PresetReports;

class ConvenienceFees extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'convenience_fees';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('convenience_fees.json');
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }
}
