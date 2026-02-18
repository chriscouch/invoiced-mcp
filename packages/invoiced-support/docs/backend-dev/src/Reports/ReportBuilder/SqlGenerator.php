<?php

namespace App\Reports\ReportBuilder;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Sql\Clauses\FromClause;
use App\Reports\ReportBuilder\Sql\Clauses\GroupByClause;
use App\Reports\ReportBuilder\Sql\Clauses\JoinClause;
use App\Reports\ReportBuilder\Sql\Clauses\LimitClause;
use App\Reports\ReportBuilder\Sql\Clauses\OrderByClause;
use App\Reports\ReportBuilder\Sql\Clauses\SelectClause;
use App\Reports\ReportBuilder\Sql\Clauses\WhereClause;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

/**
 * Converts a report data query into a SQL statement.
 */
final class SqlGenerator
{
    /**
     * Generates SQL and parameters for a report data query.
     *
     * @throws ReportException
     */
    public static function generate(DataQuery $query, SqlContext $context): string
    {
        // Compile each clause into a SQL query
        return join(' ', array_filter([
            SelectClause::makeSql($query, $context),
            FromClause::makeSql($query, $context),
            JoinClause::makeSql($query, $context),
            WhereClause::makeSql($query, $context),
            GroupByClause::makeSql($query, $context),
            OrderByClause::makeSql($query, $context),
            LimitClause::makeSql($query, $context),
        ]));
    }
}
