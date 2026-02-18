<?php

namespace App\Reports\PresetReports;

class PromiseToPays extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'promise_to_pays';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('promise_to_pays.json');
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        return $parameters;
    }
}
