<?php

namespace App\Reports\ReportBuilder\Sql\Expressions;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use App\Reports\ReportBuilder\ValueObjects\ConstantExpression;
use App\Reports\ReportBuilder\ValueObjects\ExpressionList;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

final class ExpressionHelper
{
    /**
     * @throws ReportException
     */
    public static function makeSql(ExpressionInterface $expression, SqlContext $context, ?Table $table = null): string
    {
        if ($expression instanceof FieldReferenceExpression) {
            return FieldReferenceHelper::makeSql($expression, $context);
        }

        if ($expression instanceof FunctionExpression) {
            return FunctionHelper::makeSql($expression, $table, $context);
        }

        if ($expression instanceof ConstantExpression) {
            return ConstantExpressionHelper::makeSql($expression);
        }

        if ($expression instanceof ExpressionList) {
            return self::makeListSql($expression, $context);
        }

        if ($expression instanceof FilterCondition) {
            return ConditionHelper::makeSql($expression, $context);
        }

        throw new ReportException('Unrecognized expression item type');
    }

    /**
     * @throws ReportException
     */
    private static function makeListSql(ExpressionList $item, SqlContext $context): string
    {
        $expressions = [];
        foreach ($item->expressions as $expression) {
            $expressions[] = self::makeSql($expression, $context);
        }

        $sql = join(' ', $expressions);

        return count($expressions) > 1 ? "($sql)" : $sql;
    }
}
