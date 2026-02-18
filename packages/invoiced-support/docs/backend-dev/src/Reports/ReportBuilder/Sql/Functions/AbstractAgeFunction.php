<?php

namespace App\Reports\ReportBuilder\Sql\Functions;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\FunctionInterface;
use App\Reports\ReportBuilder\Sql\Expressions\ExpressionHelper;
use App\Reports\ReportBuilder\ValueObjects\ExpressionList;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

abstract class AbstractAgeFunction implements FunctionInterface
{
    protected static function makeAgeSql(FieldReferenceExpression $dateArgument, int $asOfDate, ?Table $table, SqlContext $context): string
    {
        // Convert Date and DateTime columns to a UNIX timestamp
        $type = $dateArgument->getType();
        if ((ColumnType::Date == $type && 'U' != $dateArgument->dateFormat) || (ColumnType::DateTime == $type && 'U' != $dateArgument)) {
            $dateArgument = new FunctionExpression(
                'unix_timestamp',
                new ExpressionList([$dateArgument]),
                ColumnType::Integer
            );
        }

        $dateColumn = ExpressionHelper::makeSql($dateArgument, $context, $table);

        $asOfDate = $asOfDate > 0 ? $asOfDate : time();

        return "FLOOR(($asOfDate - $dateColumn) / 86400)";
    }
}
