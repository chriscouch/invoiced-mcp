<?php

namespace App\Reports\ReportBuilder\Sql\Functions;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Sql\Expressions\ExpressionHelper;
use App\Reports\ReportBuilder\Sql\Expressions\FieldReferenceHelper;
use App\Reports\ReportBuilder\ValueObjects\ConstantExpression;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Function: AGE_RANGE(date, return_value, lower_bound, upper_bound[, as_of_date]).
 */
final class AgeRangeFunction extends AbstractAgeFunction
{
    public static function makeSql(FunctionExpression $function, ?Table $table, SqlContext $context): string
    {
        $arguments = $function->arguments;
        if (!($arguments[0] instanceof FieldReferenceExpression) || !($arguments[2] instanceof ConstantExpression) || !($arguments[3] instanceof ConstantExpression)) {
            throw new ReportException("Invalid {$function->functionName}() format");
        }

        $returnValue = ExpressionHelper::makeSql($arguments[1], $context);
        $lowerBound = $arguments[2]->value;
        $upperBound = $arguments[3]->value;

        $dateArgument = $arguments[0];
        $asOfDate = 5 == count($arguments) && $arguments[4] instanceof ConstantExpression ? (int) $arguments[4]->value : 0;
        $ageFormula = self::makeAgeSql($dateArgument, $asOfDate, $table, $context);

        if (-1 == $lowerBound) {
            $dateColumn = FieldReferenceHelper::makeSql($dateArgument, $context);

            return "CASE WHEN $ageFormula <= -1 OR $dateColumn IS NULL THEN $returnValue ELSE 0 END";
        }

        if ($upperBound <= 0) {
            return "CASE WHEN $ageFormula >= $lowerBound THEN $returnValue ELSE 0 END";
        }

        return "CASE WHEN $ageFormula BETWEEN $lowerBound AND $upperBound THEN $returnValue ELSE 0 END";
    }
}
