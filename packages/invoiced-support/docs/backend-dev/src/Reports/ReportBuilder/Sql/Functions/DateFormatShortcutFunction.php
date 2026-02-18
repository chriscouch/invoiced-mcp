<?php

namespace App\Reports\ReportBuilder\Sql\Functions;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\FunctionInterface;
use App\Reports\ReportBuilder\Sql\Expressions\ExpressionHelper;
use App\Reports\ReportBuilder\Sql\Expressions\FunctionHelper;
use App\Reports\ReportBuilder\ValueObjects\ConstantExpression;
use App\Reports\ReportBuilder\ValueObjects\ExpressionList;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Functions:
 *   DAY(date)
 *   WEEK(date)
 *   MONTH(date)
 *   QUARTER(date)
 *   YEAR(date).
 */
class DateFormatShortcutFunction implements FunctionInterface
{
    private const DATE_FORMAT = [
        'day' => '"%Y-%m-%d"',
        'week' => '"%X-%V"',
        'month' => '"%Y%m"',
        'quarter' => '"%yQ"',
        'year' => '"%Y"',
    ];

    public static function makeSql(FunctionExpression $function, ?Table $table, SqlContext $context): string
    {
        $functionName = $function->functionName;
        $functionArguments = $function->arguments;
        $dateArgument = $functionArguments[0];

        // Convert UNIX timestamp columns to a DateTime value
        if ($dateArgument instanceof FieldReferenceExpression) {
            $type = $dateArgument->getType();
            if ((ColumnType::Date == $type && 'U' == $dateArgument->dateFormat) || (ColumnType::DateTime == $type && 'U' == $dateArgument->dateFormat)) {
                $dateArgument = new FunctionExpression(
                    'from_unixtime',
                    new ExpressionList([$dateArgument]),
                    ColumnType::DateTime
                );
            }
        }

        $formatArguments = new ExpressionList([
            $dateArgument,
            new ConstantExpression(self::DATE_FORMAT[$functionName], false),
        ]);
        $formatFunction = new FunctionExpression('date_format', $formatArguments, ColumnType::String);

        // The quarter cannot be obtained with just DATE_FORMAT(), it requires more complex SQL
        if ('quarter' == $functionName) {
            $quarterFunction = new ConstantExpression('QUARTER('.ExpressionHelper::makeSql($dateArgument, $context).')', false);
            $concatArguments = new ExpressionList([
                $formatFunction,
                $quarterFunction,
            ]);
            $formatFunction = new FunctionExpression('concat', $concatArguments, ColumnType::String);
        }

        return FunctionHelper::makeSql($formatFunction, $table, $context);
    }
}
