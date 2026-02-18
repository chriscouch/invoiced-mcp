<?php

namespace App\Reports\ReportBuilder\Sql\VirtualTables;

use App\Reports\ReportBuilder\Interfaces\TableFilterConditionsInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\Table;

final class DiscountsTableFilter implements TableFilterConditionsInterface
{
    public static function makeFilterConditions(Table $table): array
    {
        return [
            new FilterCondition(new FieldReferenceExpression($table, 'type'), '=', 'discount'),
        ];
    }
}
