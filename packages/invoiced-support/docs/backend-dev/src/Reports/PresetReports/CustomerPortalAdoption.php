<?php

namespace App\Reports\PresetReports;

class CustomerPortalAdoption extends AbstractAgingReport
{
    public static function getId(): string
    {
        return 'customer_portal_adoption';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('customer_portal_adoption.json');
    }
}
