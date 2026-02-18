<?php

namespace App\Reports\ReportBuilder\Sql\Functions;

use App\Reports\Enums\ColumnType;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use App\Reports\ReportBuilder\Interfaces\FunctionInterface;
use App\Reports\ReportBuilder\SqlGenerator;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\Fields;
use App\Reports\ReportBuilder\ValueObjects\Filter;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\Group;
use App\Reports\ReportBuilder\ValueObjects\Joins;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ReportBuilder\ValueObjects\Sort;
use App\Reports\ReportBuilder\ValueObjects\SortField;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Functions:
 *   first_value(select, parent_reference, reference, order_by)
 *   last_value(select, parent_reference, reference, order_by).
 */
final class SubSelectFunction implements FunctionInterface
{
    /**
     * @throws ReportException
     */
    public static function makeSql(FunctionExpression $function, ?Table $table, SqlContext $context): string
    {
        if (!$table) {
            throw new ReportException('Missing table reference in '.$function->functionName.' function');
        }

        /** @var FieldReferenceExpression[] $arguments */
        $arguments = $function->arguments->expressions;

        $subSelectTable = new Table($arguments[0]->table->object, 'subselect');

        // Replace the table on the subselect fields to use the subselect table
        $arguments[0] = self::replaceArgumentTable($function, $arguments[0], $subSelectTable, 'column');
        $arguments[2] = self::replaceArgumentTable($function, $arguments[2], $subSelectTable, 'reference');
        $arguments[3] = self::replaceArgumentTable($function, $arguments[3], $subSelectTable, 'order_by');

        // Compile clauses into a finalized query
        $subQuery = self::buildSubQuery($function, $arguments, $table, $subSelectTable);
        $sql = SqlGenerator::generate($subQuery, $context);

        return "($sql)";
    }

    /**
     * Rebuilds a field reference to use the subselect table.
     */
    private static function replaceArgumentTable(FunctionExpression $function, ExpressionInterface $arg, Table $subSelectTable, string $argName): FieldReferenceExpression
    {
        if (!($arg instanceof FieldReferenceExpression)) {
            throw new ReportException("The $argName argument for {$function->functionName}() format must be a field reference");
        }

        if ($arg->table->object != $subSelectTable->object) {
            throw new ReportException("The $argName argument for {$function->functionName}() must reference a field on the {$subSelectTable->object} object");
        }

        return new FieldReferenceExpression(
            table: $subSelectTable,
            id: $arg->id,
            type: $arg->getType(),
            name: $arg->getName(),
            metadataObject: $arg->metadataObject,
            shouldSummarize: $arg->shouldSummarize(),
            dateFormat: $arg->dateFormat,
        );
    }

    private static function buildSubQuery(FunctionExpression $function, array $arguments, Table $table, Table $subSelectTable): DataQuery
    {
        $ascending = 'first_value' === $function->functionName;

        return new DataQuery(
            $subSelectTable,
            new Joins([]),
            new Fields([new SelectColumn($arguments[0], type: $arguments[0]->getType() ?? ColumnType::String)]),
            new Filter([
                new FilterCondition($arguments[2], '=', $arguments[1]),
                new FilterCondition(
                    new FieldReferenceExpression($arguments[0]->table, 'tenant_id'),
                    '=',
                    new FieldReferenceExpression($table, 'tenant_id')
                ),
            ]),
            new Group([]),
            new Sort([new SortField($arguments[3], $ascending)]),
            1,
            false
        );
    }
}
