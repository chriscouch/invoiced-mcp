<?php

namespace App\Reports\PresetReports;

class SalesByItem extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'sales_by_item';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('sales_by_item.json');
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }
}
