<?php

namespace App\Reports\ReportBuilder\Sql\Functions;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\ConstantExpression;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Function: AGE(date[, as_of_date]).
 */
final class AgeFunction extends AbstractAgeFunction
{
    public static function makeSql(FunctionExpression $function, ?Table $table, SqlContext $context): string
    {
        $arguments = $function->arguments;
        if (!($arguments[0] instanceof FieldReferenceExpression)) {
            throw new ReportException("Invalid {$function->functionName}() format");
        }

        $asOfDate = 2 == count($arguments) && $arguments[1] instanceof ConstantExpression ? (int) $arguments[1]->value : 0;

        return self::makeAgeSql($arguments[0], $asOfDate, $table, $context);
    }
}
