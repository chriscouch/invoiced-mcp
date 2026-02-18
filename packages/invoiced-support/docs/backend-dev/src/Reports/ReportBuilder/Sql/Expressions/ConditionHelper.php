<?php

namespace App\Reports\ReportBuilder\Sql\Expressions;

use App\Reports\Enums\ColumnType;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use App\Reports\ReportBuilder\Sql\Types\DateTimeType;
use App\Reports\ReportBuilder\Sql\Types\DateType;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use Carbon\CarbonImmutable;

final class ConditionHelper
{
    /**
     * @throws ReportException
     */
    public static function makeSql(FilterCondition $condition, SqlContext $context): string
    {
        // Handle AND / OR
        $operator = $condition->operator;
        $value = $condition->value;
        if (in_array($operator, ['and', 'or'])) {
            $subConditions = [];
            foreach ($value as $subCondition) {
                $subConditions[] = self::makeSql($subCondition, $context);
            }

            return '('.join(' '.strtoupper($operator).' ', $subConditions).')';
        }

        $field = $condition->field;
        if (!$field) {
            throw new ReportException('Condition is missing field');
        }

        // Handle operators with special syntax
        if ('<>' == $operator && null === $value) {
            return self::isNotNull($field, $context);
        }

        if ('=' == $operator && null === $value) {
            return self::isNull($field, $context);
        }

        if (in_array($operator, ['=', '<>']) && is_array($value)) {
            return self::in('<>' == $operator, $field, $value, $context);
        }

        $parameters = $context->getParameters();
        if (is_string($value) && str_starts_with($value, '$') && '=' === $operator && isset($parameters[$value]) && isset($parameters[$value.'Nullable']) && $parameters[$value.'Nullable']) {
            return '('.self::basicOperator($field, $operator, self::injectParameters($field, $parameters, $value), $context).' OR '.self::isNull($field, $context).')';
        }

        // Before using the filter value we need to
        // replace any report parameters with their values.
        $value = self::injectParameters($field, $parameters, $value);

        // If the column queried is a date type column then
        // convert the date in the query to a UNIX timestamp.
        // Date values will be in the format YYYY-MM-DD
        if (ColumnType::Date == $field->getType()) {
            [$operator, $value] = self::parseDateValue($field, $operator, $value);
        } elseif (ColumnType::DateTime == $field->getType()) {
            $value = self::parseDateTimeValue($field, $operator, $value);
        }

        // BETWEEN / NOT BETWEEN
        if (in_array($operator, ['not_between', 'between'])) {
            return self::between($field, $operator, $value, $context);
        }

        // CONTAINS / NOT CONTAINS
        if (in_array($operator, ['contains', 'not_contains'])) {
            return self::contains($field, $operator, $value, $context);
        }

        // special case for negated metadata sub select
        if (!($value instanceof ExpressionInterface) && ($field instanceof FieldReferenceExpression) && $field->metadataObject && '<>' === $operator) {
            $field->withValue($value);
            $expr = ExpressionHelper::makeSql($field, $context);

            return $expr.' IS NULL ';
        }

        // Handles operations with infix syntax: operand operator value
        // e.g. =, <>, and comparison
        return self::basicOperator($field, $operator, $value, $context);
    }

    /**
     * @throws ReportException
     */
    private static function isNotNull(ExpressionInterface $field, SqlContext $context): string
    {
        return ExpressionHelper::makeSql($field, $context).' IS NOT NULL';
    }

    /**
     * @throws ReportException
     */
    private static function isNull(ExpressionInterface $field, SqlContext $context): string
    {
        return ExpressionHelper::makeSql($field, $context).' IS NULL';
    }

    /**
     * @throws ReportException
     */
    private static function in(bool $isNot, ExpressionInterface $field, array $value, SqlContext $context): string
    {
        $expr = ExpressionHelper::makeSql($field, $context);
        $context->addParams(array_values($value)); // must happen after expression is generated

        return $expr.
            ($isNot ? ' NOT' : '').' IN ('.
            join(',', array_fill(0, count($value), '?')).
            ')';
    }

    /**
     * Injects report parameters into the value. Report parameters
     * start with `$`. Example: $currency.
     *
     * @throws ReportException
     */
    private static function injectParameters(ExpressionInterface $field, array $parameters, mixed $value): mixed
    {
        if (is_string($value) && str_starts_with($value, '$')) {
            if (!isset($parameters[$value])) {
                if ('$now' == $value) {
                    if (ColumnType::Date == $field->getType()) {
                        return CarbonImmutable::now()->format('Y-m-d');
                    }

                    return CarbonImmutable::now()->format('Y-m-d H:i:s');
                }

                throw new ReportException('Missing report parameter: '.$value);
            }

            return $parameters[$value];
        }

        return $value;
    }

    /**
     * @throws ReportException
     */
    private static function parseDateValue(ExpressionInterface $field, string $operator, mixed $value): array
    {
        // Convert = and <> to the between operator
        if (in_array($operator, ['=', '<>'])) {
            $operator = '=' == $operator ? 'between' : 'not_between';
            $value = [
                'start' => $value,
                'end' => $value,
            ];
        }

        if (!is_string($value) && !is_array($value)) {
            return [$operator, $value];
        }

        if (is_array($value)) {
            // Convert inequality operator to use value at start or end of range.
            // Less than will use the end of date range.
            // Greater than will use the start of date range.
            if (in_array($operator, ['<', '<='])) {
                return [$operator, DateType::formatInput($field, $operator, $value['end'])];
            }

            if (in_array($operator, ['>', '>='])) {
                return [$operator, DateType::formatInput($field, $operator, $value['start'])];
            }

            return [
                $operator,
                [
                    DateType::formatInput($field, '>=', $value['start']),
                    DateType::formatInput($field, '<=', $value['end']),
                ],
            ];
        }

        return [$operator, DateType::formatInput($field, $operator, $value)];
    }

    /**
     * @throws ReportException
     */
    private static function parseDateTimeValue(ExpressionInterface $field, string $operator, mixed $value): mixed
    {
        if (!is_string($value) && !is_array($value)) {
            return $value;
        }

        if (is_array($value)) {
            // Convert inequality operator to use value at start or end of range.
            // Less than will use the end of date range.
            // Greater than will use the start of date range.
            if (in_array($operator, ['<', '<='])) {
                return [$operator, DateTimeType::formatInput($field, $operator, $value['end'])];
            }

            if (in_array($operator, ['>', '>='])) {
                return [$operator, DateTimeType::formatInput($field, $operator, $value['start'])];
            }

            return [
                DateTimeType::formatInput($field, '>=', $value['start']),
                DateTimeType::formatInput($field, '<=', $value['end']),
            ];
        }

        return DateTimeType::formatInput($field, $operator, $value);
    }

    /**
     * @throws ReportException
     */
    private static function between(ExpressionInterface $field, string $operator, mixed $value, SqlContext $context): string
    {
        if (!is_array($value) || 2 != count($value)) {
            throw new ReportException('Invalid between value');
        }

        $expr = ExpressionHelper::makeSql($field, $context);
        $context->addParam($value[0]); // must happen after expression is generated
        $context->addParam($value[1]); // must happen after expression is generated

        return $expr.('not_between' == $operator ? ' NOT' : '').' BETWEEN ? AND ?';
    }

    /**
     * @throws ReportException
     */
    private static function contains(ExpressionInterface $field, string $operator, mixed $value, SqlContext $context): string
    {
        if (!is_string($value)) {
            return new ReportException('Invalid contains value');
        }

        $expr = ExpressionHelper::makeSql($field, $context);
        $context->addParam("%$value%"); // must happen after expression is generated

        return $expr.('not_contains' == $operator ? ' NOT' : '').' LIKE ?';
    }

    /**
     * @throws ReportException
     */
    private static function basicOperator(ExpressionInterface $field, string $operator, mixed $value, SqlContext $context): string
    {
        if ($value instanceof ExpressionInterface) {
            return ExpressionHelper::makeSql($field, $context).$operator.ExpressionHelper::makeSql($value, $context);
        }

        // Handles operations with syntax: operand operator value
        $expr = ExpressionHelper::makeSql($field, $context);
        $context->addParam($value); // must happen after expression is generated

        return $expr.$operator.'?';
    }
}
