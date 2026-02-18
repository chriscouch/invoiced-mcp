<?php

namespace App\Reports\ReportBuilder\Sql\VirtualTables;

use App\Reports\ReportBuilder\Interfaces\TableFilterConditionsInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\Table;

final class LineItemsTableFilter implements TableFilterConditionsInterface
{
    public static function makeFilterConditions(Table $table): array
    {
        $parentType = str_replace('_line_item', '', $table->object);
        if ('pending' == $parentType) {
            $parentType = 'customer';
        }

        return [
            new FilterCondition(new FieldReferenceExpression($table, $parentType.'_id'), '<>', null),
        ];
    }
}
