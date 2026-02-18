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
 * Table: SaleLineItems.
 *
 * Generates a derived table statement for sale line items. This
 * table is the union of invoice and credit note line items. Credit note amounts
 * are negated because they are crediting the amount sold.
 */
final class SaleLineItemTable implements VirtualTableInterface
{
    use SalesTableTrait;

    const TABLE_NAME = 'SaleLineItems';

    private static function getObjectName(): string
    {
        return 'sale_line_item';
    }

    public static function makeSql(DataQuery $query, SqlContext $context): string
    {
        $invoiceSql = self::makeSubQuerySql('invoice_line_item', self::makeInvoiceColumns(), $query, $context);
        $creditNoteSql = self::makeSubQuerySql('credit_note_line_item', self::makeCreditNoteColumns(), $query, $context);
        // This is needed because the Sales table alias is not used by the join
        $invoiceSql = str_replace($context->getTableAlias(new Table('sale')), $context->getTableAlias(new Table('invoice')), $invoiceSql);
        $creditNoteSql = str_replace($context->getTableAlias(new Table('sale')), $context->getTableAlias(new Table('credit_note')), $creditNoteSql);

        return '('.$invoiceSql.' UNION ALL '.$creditNoteSql.')';
    }

    /**
     * @return SelectColumn[]
     */
    public static function makeInvoiceColumns(): array
    {
        return [
            new SelectColumn(new ConstantExpression('"invoice_line_item"', false), alias: 'object_type'),
            new SelectColumn(new ConstantExpression('"invoice"', false), alias: 'sale_type'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'invoice_id'), alias: 'sale_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'tenant_id'), alias: 'tenant_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'amount'), alias: 'amount'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'catalog_item_id'), alias: 'catalog_item_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'created_at'), alias: 'created_at'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'description'), alias: 'description'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'discountable'), alias: 'discountable'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'id'), alias: 'id'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'name'), alias: 'name'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'period_end'), alias: 'period_end'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'period_start'), alias: 'period_start'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'plan'), alias: 'plan'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'plan_id'), alias: 'plan_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'prorated'), alias: 'prorated'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'quantity'), alias: 'quantity'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'subscription_id'), alias: 'subscription_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'taxable'), alias: 'taxable'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'type'), alias: 'type'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'unit_cost'), alias: 'unit_cost'),
            new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'updated_at'), alias: 'updated_at'),
        ];
    }

    /**
     * @return SelectColumn[]
     */
    public static function makeCreditNoteColumns(): array
    {
        return [
            new SelectColumn(new ConstantExpression('"credit_note_line_item"', false), alias: 'object_type'),
            new SelectColumn(new ConstantExpression('"credit_note"', false), alias: 'sale_type'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'credit_note_id'), alias: 'sale_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'tenant_id'), alias: 'tenant_id'),
            new SelectColumn(new ExpressionList([new ConstantExpression('-', false), new FieldReferenceExpression(new Table('credit_note_line_item'), 'amount')]), alias: 'amount'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'catalog_item_id'), alias: 'catalog_item_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'created_at'), alias: 'created_at'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'description'), alias: 'description'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'discountable'), alias: 'discountable'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'id'), alias: 'id'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'name'), alias: 'name'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'period_end'), alias: 'period_end'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'period_start'), alias: 'period_start'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'plan'), alias: 'plan'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'plan_id'), alias: 'plan_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'prorated'), alias: 'prorated'),
            new SelectColumn(new ExpressionList([new ConstantExpression('-', false), new FieldReferenceExpression(new Table('credit_note_line_item'), 'quantity')]), alias: 'quantity'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'subscription_id'), alias: 'subscription_id'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'taxable'), alias: 'taxable'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'type'), alias: 'type'),
            new SelectColumn(new ExpressionList([new ConstantExpression('-', false), new FieldReferenceExpression(new Table('credit_note_line_item'), 'unit_cost')]), alias: 'unit_cost'),
            new SelectColumn(new FieldReferenceExpression(new Table('credit_note_line_item'), 'updated_at'), alias: 'updated_at'),
        ];
    }

    public static function makeReference(SqlContext $context, Table $table): string
    {
        $tableAlias = $context->getTableAlias($table);

        return "CONCAT($tableAlias.object_type,\"-\",$tableAlias.id) AS sale_line_item_reference";
    }
}
