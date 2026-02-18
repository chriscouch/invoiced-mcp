<?php

namespace App\Reports\ReportBuilder\Sql\Expressions;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Interfaces\FunctionInterface;
use App\Reports\ReportBuilder\Sql\Functions\AgeFunction;
use App\Reports\ReportBuilder\Sql\Functions\AgeRangeFunction;
use App\Reports\ReportBuilder\Sql\Functions\CaseFunction;
use App\Reports\ReportBuilder\Sql\Functions\CountDistinctFunction;
use App\Reports\ReportBuilder\Sql\Functions\CountFunction;
use App\Reports\ReportBuilder\Sql\Functions\DateAddSubFunction;
use App\Reports\ReportBuilder\Sql\Functions\DateFormatShortcutFunction;
use App\Reports\ReportBuilder\Sql\Functions\SubSelectFunction;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * This class converts functions into valid SQL. If a function
 * requires a special syntax then it will be handled here.
 */
final class FunctionHelper
{
    private const FUNCTION_HANDLERS = [
        'age' => AgeFunction::class,
        'age_range' => AgeRangeFunction::class,
        'case' => CaseFunction::class,
        'count' => CountFunction::class,
        'count_distinct' => CountDistinctFunction::class,
        'date_add' => DateAddSubFunction::class,
        'date_sub' => DateAddSubFunction::class,
        'day' => DateFormatShortcutFunction::class,
        'first_value' => SubSelectFunction::class,
        'last_value' => SubSelectFunction::class,
        'month' => DateFormatShortcutFunction::class,
        'quarter' => DateFormatShortcutFunction::class,
        'week' => DateFormatShortcutFunction::class,
        'year' => DateFormatShortcutFunction::class,
    ];

    /**
     * @throws ReportException
     */
    public static function makeSql(FunctionExpression $function, ?Table $table, SqlContext $context): string
    {
        // Some functions have a different SQL syntax that requires
        // special handling.
        $functionName = $function->functionName;
        if (isset(self::FUNCTION_HANDLERS[$functionName])) {
            /** @var FunctionInterface $class */
            $class = self::FUNCTION_HANDLERS[$functionName];

            return $class::makeSql($function, $table, $context);
        }

        return strtoupper($functionName).'('.self::buildFunctionArguments($function, $context).')';
    }

    /**
     * @throws ReportException
     */
    private static function buildFunctionArguments(FunctionExpression $function, SqlContext $context): string
    {
        $arguments = [];
        foreach ($function->arguments->expressions as $argument) {
            $arguments[] = ExpressionHelper::makeSql($argument, $context);
        }

        return join(', ', $arguments);
    }
}
