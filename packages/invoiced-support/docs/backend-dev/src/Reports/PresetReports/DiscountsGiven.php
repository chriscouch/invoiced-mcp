<?php

namespace App\Reports\PresetReports;

class DiscountsGiven extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'discounts_given';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('discounts_given.json');
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }
}
