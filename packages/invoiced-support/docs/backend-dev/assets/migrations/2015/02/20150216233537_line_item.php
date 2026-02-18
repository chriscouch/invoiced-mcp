<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LineItem extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('LineItems');
        $this->addTenant($table);
        $table->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex('invoice_id')
            ->addColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex('customer_id')
            ->addColumn('estimate_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex('estimate_id')
            ->addColumn('credit_note_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex('credit_note_id')
            ->addColumn('catalog_item', 'string', ['null' => true, 'default' => null])
            ->addColumn('catalog_item_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('plan', 'string', ['default' => null, 'null' => true])
            ->addColumn('plan_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('type', 'string', ['length' => 50, 'null' => true, 'default' => null])
            ->addColumn('name', 'string')
            ->addColumn('quantity', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('unit_cost', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addColumn('order', 'integer')
            ->addColumn('discountable', 'boolean', ['default' => true])
            ->addColumn('taxable', 'boolean', ['default' => true])
            ->addColumn('subscription_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('period_start', 'integer', ['null' => true, 'default' => null])
            ->addColumn('period_end', 'integer', ['null' => true, 'default' => null])
            ->addColumn('prorated', 'boolean')
            ->addTimestamps()
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('estimate_id', 'Estimates', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('credit_note_id', 'CreditNotes', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
