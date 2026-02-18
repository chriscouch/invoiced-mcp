<?php

namespace App\Reports\ReportBuilder\Sql\Clauses;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ReportConfiguration;
use App\Reports\ReportBuilder\Interfaces\ClauseInterface;
use App\Reports\ReportBuilder\Interfaces\VirtualTableInterface;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\JoinCondition;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

final class JoinClause implements ClauseInterface
{
    public static function makeSql(DataQuery $query, SqlContext $context): string
    {
        $sql = [];
        foreach ($query->joins->conditions as $join) {
            $sql[] = self::makeJoin($join, $query, $context);
        }

        return implode(' ', $sql);
    }

    /**
     * @throws ReportException
     */
    private static function makeJoin(JoinCondition $join, DataQuery $query, SqlContext $context): string
    {
        $joinTable = $join->joinTable;
        $joinObject = $joinTable->object;
        $joinTablenameSql = FromClause::getTablename($joinTable);
        $joinTableAlias = $context->getTableAlias($joinTable);
        if (isset(FromClause::VIRTUAL_TABLES[$joinObject])) {
            /** @var VirtualTableInterface $table */
            $table = FromClause::VIRTUAL_TABLES[$joinObject];
            $joinTablenameSql = $table::makeSql($query, $context);
        }

        $sql = '';
        $parentTableAlias = $context->getTableAlias($join->parentTable);
        $parentColumn = $join->parentColumn;

        // if there is a join through condition then that is added first
        if ($join->joinThroughTable) {
            $joinThroughTableAlias = $context->getTableAlias(new Table($join->joinThroughTable));
            $sql .= $join->joinType.' '.$join->joinThroughTable.' '.$joinThroughTableAlias;
            $sql .= ' ON '.self::makeDefaultCondition($parentTableAlias, $join->parentColumn, $joinThroughTableAlias, $join->joinColumn).' ';
            // The next join condition needs to be pointed to the join through table instead of the original parent table
            $parentTableAlias = $joinThroughTableAlias;
            $parentColumn = (string) $join->joinThroughColumn;
        }

        $sql .= $join->joinType.' '.$joinTablenameSql.' '.$joinTableAlias;

        // build the conditions for the join
        $conditions = [
            self::makeDefaultCondition($parentTableAlias, $parentColumn, $joinTableAlias, $join->joinColumn),
        ];

        // handle polymorphic relationships
        if ($join->parentTypeColumn) {
            $conditions[] = self::makePolymorphicCondition($join, $joinTableAlias, $parentTableAlias);
        }

        // special case for objects which need a tenant_id constraint
        $objectConfiguration = ReportConfiguration::get()->getObject($joinObject);
        if (isset($objectConfiguration['join_with_tenant_id'])) {
            $conditions[] = self::makeTenantCondition($joinTableAlias, $parentTableAlias);
        }

        $sql .= ' ON '.join(' AND ', $conditions);

        return $sql;
    }

    private static function makeDefaultCondition(string $parentTablename, string $parentColumn, string $joinTablename, string $joinColumn): string
    {
        return $parentTablename.'.'.$parentColumn.'='.$joinTablename.'.'.$joinColumn;
    }

    /**
     * Creates a join condition for a polymorphic relationship.
     * A polymorphic relationship has a type and an ID component
     * to indicate which table needs to be joined.
     */
    private static function makePolymorphicCondition(JoinCondition $join, string $joinTablename, string $parentTablename): string
    {
        if ($joinTypeColumn = $join->joinTypeColumn) {
            // This mode uses a column from the joined table as the condition.
            // Used when the join table is itself polymorphic.
            // e.g. SalesLineItem.sales_type=Sales.type
            $conditionValue = $joinTablename.'.'.$joinTypeColumn;
        } else {
            // This mode uses the join object name as the condition.
            // Used when the join object is specified/static in the
            // configuration.
            // e.g. Customers.payment_source_type="card"
            $conditionValue = '"'.$join->joinTable->object.'"';
        }

        return $parentTablename.'.'.$join->parentTypeColumn.'='.$conditionValue;
    }

    private static function makeTenantCondition(string $joinTablename, string $parentTablename): string
    {
        return $joinTablename.'.tenant_id='.$parentTablename.'.tenant_id';
    }
}
