<?php

namespace App\Reports\ReportBuilder\Sql\VirtualTables;

use App\Reports\ReportBuilder\Interfaces\VirtualTableInterface;
use App\Reports\ReportBuilder\ValueObjects\ConstantExpression;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\ExpressionList;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Table: Sales.
 *
 * Generates a derived table statement for sales transactions. This
 * table is the union of invoices and credit notes. Credit note amounts
 * are negated because they are crediting the amount sold.
 */
final class SalesTable implements VirtualTableInterface
{
    use SalesTableTrait;

    const TABLE_NAME = 'Sales';

    private static function getObjectName(): string
    {
        return 'sale';
    }

    public static function makeSql(DataQuery $query, SqlContext $context): string
    {
        $invoiceSql = self::makeSubQuerySql('invoice', self::makeInvoiceColumns(), $query, $context);
        $creditNoteSql = self::makeSubQuerySql('credit_note', self::makeCreditNoteColumns(), $query, $context);

        return '('.$invoiceSql.' UNION ALL '.$creditNoteSql.')';
    }

    /**
     * @return SelectColumn[]
     */
    public static function makeInvoiceColumns(): array
    {
        return [
            new SelectColumn(new ConstantExpression('"invoice"', false), alias: '`type`'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), alias: 'tenant_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'customer'), alias: 'customer'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'balance'), alias: 'balance'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'closed'), alias: 'closed'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'created_at'), alias: 'created_at'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'currency'), alias: 'currency'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'date'), alias: 'date'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'due_date'), alias: 'due_date'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'date_voided'), alias: 'date_voided'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'draft'), alias: 'draft'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'id'), alias: 'id'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'name'), alias: 'name'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'notes'), alias: 'notes'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'), alias: 'number'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'paid'), alias: 'paid'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'purchase_order'), alias: 'purchase_order'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'sent'), alias: 'sent'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'status'), alias: 'status'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'subtotal'), alias: 'subtotal'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'total'), alias: 'total'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'updated_at'), alias: 'updated_at'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'viewed'), alias: 'viewed'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'voided'), alias: 'voided'),
        ];
    }

    /**
     * @return SelectColumn[]
     */
    public static function makeCreditNoteColumns(): array
    {
        return [
            new SelectColumn(new ConstantExpression('"credit_note"', false), alias: '`type`'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'tenant_id'), alias: 'tenant_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'customer'), alias: 'customer'),
            new SelectColumn(new ExpressionList([new ConstantExpression('-', false), new FieldReferenceExpression(new Table('credit_note'), 'balance')]), alias: 'balance'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'closed'), alias: 'closed'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'created_at'), alias: 'created_at'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'currency'), alias: 'currency'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'date'), alias: 'date'),
            new SelectColumn(new ConstantExpression('NULL', false), alias: 'due_date'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'date_voided'), alias: 'date_voided'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'draft'), alias: 'draft'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'id'), alias: 'id'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'name'), alias: 'name'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'notes'), alias: 'notes'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'number'), alias: 'number'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'paid'), alias: 'paid'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'purchase_order'), alias: 'purchase_order'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'sent'), alias: 'sent'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'status'), alias: 'status'),
            new SelectColumn(new ExpressionList([new ConstantExpression('-', false), new FieldReferenceExpression(new Table('credit_note'), 'subtotal')]), alias: 'subtotal'),
            new SelectColumn(new ExpressionList([new ConstantExpression('-', false), new FieldReferenceExpression(new Table('credit_note'), 'total')]), alias: 'total'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'updated_at'), alias: 'updated_at'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'viewed'), alias: 'viewed'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note'), 'voided'), alias: 'voided'),
        ];
    }

    public static function makeReference(SqlContext $context, Table $table): string
    {
        $tableAlias = $context->getTableAlias($table);

        return "CONCAT($tableAlias.type,\"-\",$tableAlias.id) AS sale_reference";
    }
}
