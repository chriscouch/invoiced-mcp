<?php

namespace App\Reports\ReportBuilder\Sql\Functions;

use App\Reports\ReportBuilder\Interfaces\FunctionInterface;
use App\Reports\ReportBuilder\Sql\Expressions\ExpressionHelper;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Function: COUNT([column]).
 */
final class CountFunction implements FunctionInterface
{
    public static function makeSql(FunctionExpression $function, ?Table $table, SqlContext $context): string
    {
        $functionArguments = $function->arguments;
        if (0 == count($functionArguments)) {
            return 'COUNT(*)';
        }

        return 'COUNT('.ExpressionHelper::makeSql($functionArguments[0], $context).')';
    }
}
