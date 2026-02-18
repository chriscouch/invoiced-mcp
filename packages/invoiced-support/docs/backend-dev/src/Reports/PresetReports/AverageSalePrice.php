<?php

namespace App\Reports\PresetReports;

class AverageSalePrice extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'average_sale_price';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('average_sale_price.json');
    }
}
