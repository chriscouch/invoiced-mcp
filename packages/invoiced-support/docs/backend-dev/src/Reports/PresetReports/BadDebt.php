<?php

namespace App\Reports\PresetReports;

class BadDebt extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'bad_debt';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('bad_debt.json');
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }
}
