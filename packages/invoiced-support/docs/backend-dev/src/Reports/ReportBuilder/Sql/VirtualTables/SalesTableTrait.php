<?php

namespace App\Reports\ReportBuilder\Sql\VirtualTables;

use App\Reports\ReportBuilder\SqlGenerator;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\Fields;
use App\Reports\ReportBuilder\ValueObjects\Filter;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\Group;
use App\Reports\ReportBuilder\ValueObjects\JoinCondition;
use App\Reports\ReportBuilder\ValueObjects\Joins;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ReportBuilder\ValueObjects\Sort;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

trait SalesTableTrait
{
    /**
     * @param SelectColumn[] $columns
     */
    private static function makeSubQuerySql(string $documentObject, array $columns, DataQuery $query, SqlContext $context): string
    {
        $objectName = self::getObjectName();
        $table = new Table($documentObject);

        // Only include joins and filtering if this table is the main reporting object.
        $joins = [];
        if ($objectName === $query->table->object) {
            $filter = $query->filter;

            // Rewrite any sales table joins
            $joins = [];
            foreach ($query->joins->conditions as $join) {
                if ('invoice_line_item' == $documentObject && 'sale' == $join->joinTable->object) {
                    $join = new JoinCondition($table, new Table('invoice'));
                } elseif ('credit_note_line_item' == $documentObject && 'sale' == $join->joinTable->object) {
                    $join = new JoinCondition($table, new Table('credit_note'));
                }
                $joins[] = $join;
            }
        } else {
            // Exclude all filter conditions except for the tenant ID constraint, for performance
            $tenantIdFilter = $query->filter->conditions[0];
            $filter = new Filter([
                new FilterCondition(new FieldReferenceExpression($table, 'tenant_id'), $tenantIdFilter->operator, $tenantIdFilter->value),
            ]);
        }

        $subQuery = new DataQuery(
            $table,
            new Joins($joins),
            new Fields($columns),
            $filter,
            new Group([]),
            new Sort([]),
            withReferenceColumns: false
        );

        $sql = SqlGenerator::generate($subQuery, $context);

        // Rewrite any references to the sales table with the correct alias
        return str_replace($context->getTableAlias(new Table($objectName)), $context->getTableAlias($table), $sql);
    }

    abstract private static function getObjectName(): string;
}
