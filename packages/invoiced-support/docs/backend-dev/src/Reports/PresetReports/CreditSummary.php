<?php

namespace App\Reports\PresetReports;

class CreditSummary extends AbstractNullableCurrencyReport
{
    public static function getId(): string
    {
        return 'credit_summary';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('credit_balance_summary.json');
    }
}
