<?php

namespace App\Reports\PresetReports;

class Communications extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'communications';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('communications.json');
    }
}
