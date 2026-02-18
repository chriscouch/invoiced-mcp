<?php

namespace App\Reports\PresetReports;

class FailedCharges extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'failed_charges';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('failed_charges.json');
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }
}
