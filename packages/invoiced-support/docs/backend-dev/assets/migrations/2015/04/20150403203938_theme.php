<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Theme extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Themes', ['id' => false, 'primary_key' => ['tenant_id', 'id']]);
        $this->addTenant($table);
        $table->addColumn('id', 'string', ['collation' => 'utf8_bin'])
            ->addColumn('name', 'text')
            ->addColumn('use_translations', 'boolean')
            ->addColumn('header', 'string')
            ->addColumn('header_estimate', 'string')
            ->addColumn('from_title', 'string')
            ->addColumn('to_title', 'string')
            ->addColumn('ship_to_title', 'string', ['default' => 'Ship To'])
            ->addColumn('customer_number_title', 'string')
            ->addColumn('show_customer_no', 'boolean')
            ->addColumn('invoice_number_title', 'string')
            ->addColumn('estimate_number_title', 'string')
            ->addColumn('date_title', 'string')
            ->addColumn('date_format', 'string', ['length' => 10, 'null' => true, 'default' => null])
            ->addColumn('payment_terms_title', 'string')
            ->addColumn('due_date_title', 'string')
            ->addColumn('purchase_order_title', 'string')
            ->addColumn('show_purchase_order', 'boolean')
            ->addColumn('quantity_header', 'string')
            ->addColumn('item_header', 'string')
            ->addColumn('unit_cost_header', 'string')
            ->addColumn('amount_header', 'string')
            ->addColumn('subtotal_title', 'string')
            ->addColumn('amount_paid_title', 'string')
            ->addColumn('balance_title', 'string')
            ->addColumn('total_title', 'string')
            ->addColumn('terms_title', 'string')
            ->addColumn('notes_title', 'string')
            ->addColumn('header_receipt', 'string')
            ->addColumn('amount_title', 'string')
            ->addColumn('payment_method_title', 'string')
            ->addColumn('check_no_title', 'string')
            ->addColumn('terms', 'text', ['null' => true, 'default' => null])
            ->addColumn('estimate_footer', 'text', ['null' => true, 'default' => null])
            ->addColumn('receipt_footer', 'string')
            ->addColumn('custom_appearance', 'boolean')
            ->addColumn('template_engine', 'enum', ['values' => ['mustache', 'twig'], 'default' => 'mustache'])
            ->addColumn('invoice_template_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('estimate_template_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('statement_template_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('receipt_template_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
