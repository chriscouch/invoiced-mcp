<?php

namespace App\Reports\PresetReports;

class CustomerTimeToPay extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'customer_time_to_pay';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('customer_time_to_pay.json');
    }
}
