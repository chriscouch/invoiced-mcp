<?php

namespace App\Reports\ReportBuilder\Sql\Functions;

use App\Reports\ReportBuilder\Interfaces\FunctionInterface;
use App\Reports\ReportBuilder\Sql\Expressions\ExpressionHelper;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Functions:
 *   DATE_ADD(date, interval, unit)
 *   DATE_SUB(date, interval, unit).
 */
final class DateAddSubFunction implements FunctionInterface
{
    public static function makeSql(FunctionExpression $function, ?Table $table, SqlContext $context): string
    {
        $functionArguments = $function->arguments;
        $dateExp = $functionArguments[0];
        $intervalExp = $functionArguments[1];
        $unit = $functionArguments[2];

        return strtoupper($function->functionName).'('.ExpressionHelper::makeSql($dateExp, $context).', INTERVAL '.ExpressionHelper::makeSql($intervalExp, $context).' '.strtoupper(ExpressionHelper::makeSql($unit, $context)).')';
    }
}
