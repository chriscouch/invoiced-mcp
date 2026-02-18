<?php

namespace App\Reports\PresetReports;

class InvoicedActiveUsers extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'invoiced_active_users';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('invoiced_active_users.json');
    }
}
