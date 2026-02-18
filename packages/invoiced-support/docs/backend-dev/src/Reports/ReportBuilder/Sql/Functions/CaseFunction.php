<?php

namespace App\Reports\ReportBuilder\Sql\Functions;

use App\Reports\ReportBuilder\Interfaces\FunctionInterface;
use App\Reports\ReportBuilder\Sql\Expressions\ExpressionHelper;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Function: CASE(value, compare_value, result, [compare_value, result ...], [else_result]).
 */
class CaseFunction implements FunctionInterface
{
    public static function makeSql(FunctionExpression $function, ?Table $table, SqlContext $context): string
    {
        $functionArguments = $function->arguments;
        $sql = 'CASE '.ExpressionHelper::makeSql($functionArguments[0], $context);

        $numArguments = count($functionArguments);
        $hasElse = 0 == $numArguments % 2;

        // Comparison statements
        $comparisonArguments = array_slice($functionArguments->expressions, 1, $hasElse ? -1 : $numArguments);
        for ($i = 0; $i < floor(count($comparisonArguments) / 2); ++$i) {
            $sql .= ' WHEN '.ExpressionHelper::makeSql($comparisonArguments[$i * 2], $context);
            $sql .= ' THEN '.ExpressionHelper::makeSql($comparisonArguments[$i * 2 + 1], $context);
        }

        // ELSE statement
        if ($hasElse) {
            $sql .= ' ELSE '.ExpressionHelper::makeSql($functionArguments[$numArguments - 1], $context);
        }

        $sql .= ' END';

        return $sql;
    }
}
