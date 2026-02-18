<?php

namespace App\Reports\ReportBuilder\Sql\Clauses;

use App\Reports\ReportBuilder\Interfaces\ClauseInterface;
use App\Reports\ReportBuilder\Sql\Expressions\ExpressionHelper;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

final class OrderByClause implements ClauseInterface
{
    public static function makeSql(DataQuery $query, SqlContext $context): string
    {
        $orderBy = [];

        // Sort by any expanded group fields FIRST because they are not reflected in the query
        foreach ($query->groupBy->getExpandedFields() as $groupField) {
            $orderBy[] = trim(ExpressionHelper::makeSql($groupField->expression, $context).' '.$groupField->getDirection());
        }

        // Then add any user requested sorting
        foreach ($query->sort->fields as $sortField) {
            $orderBy[] = trim(ExpressionHelper::makeSql($sortField->expression, $context).' '.$sortField->getDirection());
        }

        if (0 == count($orderBy)) {
            return '';
        }

        return 'ORDER BY '.join(',', $orderBy);
    }
}
