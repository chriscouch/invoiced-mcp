<?php

namespace App\Reports\PresetReports;

abstract class AbstractNullableCurrencyReport extends AbstractReportBuilderReport
{
    protected function getParameters(array $parameters): array
    {
        if (!isset($parameters['$currency']) || $parameters['$currency'] === $this->company->currency || null == $parameters['$currency']) {
            $parameters['$currency'] = $this->company->currency;
            $parameters['$currencyNullable'] = true;
        }

        return $parameters;
    }
}
