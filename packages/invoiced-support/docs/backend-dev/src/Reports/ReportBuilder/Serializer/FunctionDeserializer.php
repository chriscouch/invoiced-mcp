<?php

namespace App\Reports\ReportBuilder\Serializer;

use App\Companies\Models\Company;
use App\Reports\Enums\ColumnType;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\ExpressionList;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\JoinCollector;

/**
 * Responsible for deserializing functions used within an expression.
 */
final class FunctionDeserializer
{
    public const ALLOWED_FUNCTIONS = [
        null,
        'abs',
        'age',
        'age_range',
        'avg',
        'case',
        'ceil',
        'count',
        'count_distinct',
        'date_add',
        'date_format',
        'date_sub',
        'day',
        'first_value',
        'floor',
        'from_unixtime',
        'greatest',
        'if',
        'ifnull',
        'last_value',
        'least',
        'lower',
        'max',
        'min',
        'month',
        'quarter',
        'round',
        'sum',
        'unix_timestamp',
        'upper',
        'week',
        'year',
    ];

    /**
     * The expected # arguments for each supported function
     * type. This specifies the minimum (first element) and
     * maximum (second element) number of arguments.
     */
    private const ARGUMENT_COUNT = [
        'abs' => [1, 1],
        'age' => [1, 2],
        'age_range' => [4, 5],
        'avg' => [1, 1],
        'case' => [4, 99],
        'ceil' => [1, 1],
        'count' => [0, 1],
        'count_distinct' => [1, 1],
        'date_add' => [3, 3],
        'date_format' => [2, 2],
        'date_sub' => [3, 3],
        'day' => [1, 1],
        'first_value' => [4, 4],
        'floor' => [1, 1],
        'from_unixtime' => [1, 1],
        'greatest' => [2, 99],
        'if' => [3, 3],
        'ifnull' => [2, 2],
        'last_value' => [4, 4],
        'least' => [2, 99],
        'lower' => [1, 1],
        'max' => [1, 1],
        'min' => [1, 1],
        'month' => [1, 1],
        'quarter' => [1, 1],
        'round' => [1, 2],
        'sum' => [1, 1],
        'unix_timestamp' => [1, 1],
        'upper' => [1, 1],
        'week' => [1, 1],
        'year' => [1, 1],
    ];

    /**
     * Sets the return type of the function if the
     * return type is always the same. If the return
     * type is not set here then it is inherited from
     * the function arguments.
     */
    const FUNCTION_TYPE = [
        'abs' => null,
        'age' => ColumnType::Integer,
        'age_range' => null,
        'avg' => null,
        'case' => null,
        'ceil' => ColumnType::Integer,
        'count' => ColumnType::Integer,
        'count_distinct' => ColumnType::Integer,
        'date_add' => ColumnType::Date,
        'date_format' => ColumnType::String,
        'date_sub' => ColumnType::DateTime,
        'day' => ColumnType::Day,
        'first_value' => null,
        'floor' => ColumnType::Integer,
        'from_unixtime' => ColumnType::Date,
        'greatest' => null,
        'if' => null,
        'ifnull' => null,
        'last_value' => null,
        'least' => null,
        'lower' => ColumnType::String,
        'max' => null,
        'min' => null,
        'month' => ColumnType::Month,
        'quarter' => ColumnType::Quarter,
        'round' => null,
        'sum' => null,
        'unix_timestamp' => ColumnType::Integer,
        'upper' => ColumnType::String,
        'week' => ColumnType::Week,
        'year' => ColumnType::Year,
    ];

    /**
     * @throws ReportException
     */
    public static function deserialize(string $object, string $function, array $arguments, JoinCollector $joins, Company $company): FunctionExpression
    {
        // Validate # of arguments in the function
        [$min, $max] = self::ARGUMENT_COUNT[$function];
        $n = count($arguments);
        if ($n < $min) {
            throw new ReportException($function." requires at least $min argument(s). $n argument(s) were provided.");
        } elseif ($n > $max) {
            throw new ReportException($function." accepts no more than $max argument(s). $n argument(s) were provided.");
        }

        $functionArguments = ExpressionDeserializer::deserialize($object, $arguments, $joins, $company);
        $type = self::FUNCTION_TYPE[$function];

        if (!($functionArguments instanceof ExpressionList)) {
            throw new ReportException('Invalid function arguments');
        }

        return new FunctionExpression(
            functionName: $function,
            arguments: $functionArguments,
            type: $type
        );
    }
}
