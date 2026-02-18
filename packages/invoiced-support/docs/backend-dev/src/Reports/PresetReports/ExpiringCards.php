<?php

namespace App\Reports\PresetReports;

class ExpiringCards extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'expiring_cards';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('expiring_cards.json');
    }
}
