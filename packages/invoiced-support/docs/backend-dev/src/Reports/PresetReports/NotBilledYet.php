<?php

namespace App\Reports\PresetReports;

class NotBilledYet extends AbstractNullableCurrencyReport
{
    public static function getId(): string
    {
        return 'not_billed_yet';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('not_billed_yet.json');
    }
}
