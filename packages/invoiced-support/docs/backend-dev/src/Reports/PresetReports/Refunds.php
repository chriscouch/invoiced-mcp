<?php

namespace App\Reports\PresetReports;

class Refunds extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'refunds';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('refunds.json');
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }
}
