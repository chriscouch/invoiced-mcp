<?php

namespace App\Reports\ReportBuilder\Sql\Functions;

use App\Reports\ReportBuilder\Interfaces\FunctionInterface;
use App\Reports\ReportBuilder\Sql\Expressions\ExpressionHelper;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Function: COUNT_DISTINCT(column).
 */
final class CountDistinctFunction implements FunctionInterface
{
    public static function makeSql(FunctionExpression $function, ?Table $table, SqlContext $context): string
    {
        $functionArguments = $function->arguments;

        return 'COUNT(DISTINCT '.ExpressionHelper::makeSql($functionArguments[0], $context).')';
    }
}
