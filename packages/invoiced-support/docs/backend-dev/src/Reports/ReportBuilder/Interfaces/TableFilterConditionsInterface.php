<?php

namespace App\Reports\ReportBuilder\Interfaces;

use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\Table;

interface TableFilterConditionsInterface
{
    /**
     * @return FilterCondition[]
     */
    public static function makeFilterConditions(Table $table): array;
}
