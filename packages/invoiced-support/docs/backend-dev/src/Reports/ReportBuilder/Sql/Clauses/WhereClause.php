<?php

namespace App\Reports\ReportBuilder\Sql\Clauses;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Interfaces\ClauseInterface;
use App\Reports\ReportBuilder\Interfaces\TableFilterConditionsInterface;
use App\Reports\ReportBuilder\Sql\Expressions\ConditionHelper;
use App\Reports\ReportBuilder\Sql\VirtualTables\DiscountsTableFilter;
use App\Reports\ReportBuilder\Sql\VirtualTables\LineItemsTableFilter;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

final class WhereClause implements ClauseInterface
{
    // When the sales virtual table is used the conditions are pushed down
    // to the derived table queries for performance.
    private const NO_WHERE_CLAUSE = ['sale', 'sale_line_item'];

    private const ADDED_CONDITIONS = [
        'credit_note_line_item' => LineItemsTableFilter::class,
        'estimate_line_item' => LineItemsTableFilter::class,
        'invoice_discount' => DiscountsTableFilter::class,
        'invoice_line_item' => LineItemsTableFilter::class,
        'pending_line_item' => LineItemsTableFilter::class,
        'sale_line_item' => LineItemsTableFilter::class,
    ];

    public static function makeSql(DataQuery $query, SqlContext $context): string
    {
        $conditions = $query->filter->conditions;
        if (0 == count($conditions)) {
            throw new ReportException('Missing filtering conditions');
        }

        $object = $query->table->object;
        if (in_array($object, self::NO_WHERE_CLAUSE)) {
            return '';
        }

        if (isset(self::ADDED_CONDITIONS[$object])) {
            /** @var TableFilterConditionsInterface $class */
            $class = self::ADDED_CONDITIONS[$object];
            $conditions = array_merge($conditions, $class::makeFilterConditions($query->table));
        }

        $where = [];
        foreach ($conditions as $condition) {
            $where[] = ConditionHelper::makeSql($condition, $context);
        }

        return 'WHERE '.join(' AND ', $where);
    }
}
