<?php

namespace App\Reports\ReportBuilder\Sql\Clauses;

use App\Core\Utils\Enums\ObjectType;
use App\Reports\ReportBuilder\Interfaces\ClauseInterface;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use App\Reports\ReportBuilder\Interfaces\VirtualTableInterface;
use App\Reports\ReportBuilder\Sql\Expressions\ExpressionHelper;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;
use App\Core\Orm\Model;
use RuntimeException;

final class SelectClause implements ClauseInterface
{
    public static function makeSql(DataQuery $query, SqlContext $context): string
    {
        return 'SELECT '.join(',', array_merge(
            self::buildFieldColumns($query, $context),
            self::buildReferenceColumns($query, $context),
            self::buildGroupColumns($query, $context),
        ));
    }

    /**
     * Selects each column requested in the report.
     */
    private static function buildFieldColumns(DataQuery $query, SqlContext $context): array
    {
        $columns = [];
        foreach ($query->fields->columns as $field) {
            $columns[] = self::makeColumnSql($field->expression, $context, $field->alias, $query->table);
        }

        return $columns;
    }

    /**
     * Selects a reference to each joined object in the report
     * that will be used to link values to the object it references.
     */
    private static function buildReferenceColumns(DataQuery $query, SqlContext $context): array
    {
        if (!$query->withReferenceColumns) {
            return [];
        }

        /** @var Table[] $tablesToCheck */
        $tablesToCheck = [$query->table];
        foreach ($query->joins->conditions as $join) {
            $tablesToCheck[] = $join->joinTable;
        }

        $checked = [];
        $columns = [];
        foreach ($tablesToCheck as $table) {
            $tableAlias = $table->alias;
            if (isset($checked[$tableAlias])) {
                continue;
            }

            $object = $table->object;
            if (isset(FromClause::VIRTUAL_TABLES[$object])) {
                /** @var VirtualTableInterface $virtualTable */
                $virtualTable = FromClause::VIRTUAL_TABLES[$object];
                $columns[] = $virtualTable::makeReference($context, $table);
            } else {
                try {
                    /** @var Model $model */
                    $model = ObjectType::fromTypeName($object)->modelClass();
                } catch (RuntimeException) {
                    // skip if there is not a corresponding model
                    continue;
                }

                $ids = $model::definition()->getIds();
                $alias = str_replace(['.', '-'], ['_', '_'], $tableAlias.'_reference');
                $columns[] = self::makeColumnSql(new FieldReferenceExpression($table, $ids[0]), $context, $alias);
            }

            $checked[$tableAlias] = true;
        }

        return $columns;
    }

    /**
     * Selects a column for each collapsed group by condition.
     */
    private static function buildGroupColumns(DataQuery $query, SqlContext $context): array
    {
        $columns = [];
        foreach ($query->groupBy->fields as $groupField) {
            $columns[] = self::makeColumnSql($groupField->expression, $context, $groupField->getAlias());
        }

        return $columns;
    }

    private static function makeColumnSql(ExpressionInterface $expression, SqlContext $context, string $alias, ?Table $table = null): string
    {
        return ExpressionHelper::makeSql($expression, $context, $table).' AS '.$alias;
    }
}
