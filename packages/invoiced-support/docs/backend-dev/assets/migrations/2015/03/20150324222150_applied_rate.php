<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AppliedRate extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('AppliedRates');
        $this->addTenant($table);
        $table->addColumn('parent_type', 'enum', ['values' => ['credit_note', 'estimate', 'invoice', 'line_item']])
            ->addColumn('parent_id', 'integer')
            ->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex('invoice_id')
            ->addColumn('line_item_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex('line_item_id')
            ->addColumn('estimate_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex('estimate_id')
            ->addColumn('credit_note_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex('credit_note_id')->addColumn('type', 'enum', ['values' => ['discount', 'tax', 'shipping']])
            ->addColumn('rate', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('rate_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('order', 'integer')
            ->addColumn('expires', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('parent_type')
            ->addIndex('parent_id')
            ->addIndex('type')
            ->addIndex('expires')
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('line_item_id', 'LineItems', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('estimate_id', 'Estimates', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('credit_note_id', 'CreditNotes', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
