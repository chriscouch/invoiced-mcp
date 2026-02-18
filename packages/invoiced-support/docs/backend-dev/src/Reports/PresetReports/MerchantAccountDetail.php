<?php

namespace App\Reports\PresetReports;

class MerchantAccountDetail extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'merchant_account_detail';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('merchant_account_detail.json');
    }
}
