<?php

namespace App\Reports\ReportBuilder\Sql\Clauses;

use App\Reports\ReportBuilder\Interfaces\ClauseInterface;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

final class LimitClause implements ClauseInterface
{
    public static function makeSql(DataQuery $query, SqlContext $context): string
    {
        return $query->maxResults ? 'LIMIT '.$query->maxResults : '';
    }
}
