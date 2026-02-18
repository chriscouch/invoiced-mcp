<?php

namespace App\Reports\ReportBuilder\Sql\Clauses;

use App\Reports\ReportBuilder\Interfaces\ClauseInterface;
use App\Reports\ReportBuilder\Sql\Expressions\ExpressionHelper;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

final class GroupByClause implements ClauseInterface
{
    public static function makeSql(DataQuery $query, SqlContext $context): string
    {
        $groupBy = [];
        // expanded group by fields are not reflected in the SQL query
        foreach ($query->groupBy->getCollapsedFields() as $groupField) {
            $groupBy[] = trim(ExpressionHelper::makeSql($groupField->expression, $context).' '.$groupField->getDirection());
        }

        if (0 == count($groupBy)) {
            return '';
        }

        return 'GROUP BY '.join(',', $groupBy);
    }
}
