<?php

namespace App\Reports\PresetReports;

use Carbon\CarbonImmutable;

class PaymentStatistics extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'payment_statistics';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('payment_statistics.json');
    }

    protected function getParameters(array $parameters): array
    {
        $parameters['$currency'] ??= $this->company->currency;

        // This is added for BC
        if (!isset($parameters['$dateRange'])) {
            $parameters['$dateRange'] = [
                'start' => (new CarbonImmutable('-1 year'))->toDateString(),
                'end' => CarbonImmutable::now()->toDateString(),
            ];
        }

        return $parameters;
    }
}
