<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CreditNote extends MultitenantModelMigration
{
    public function change()
    {
        // This is done in order to facilitate the change in migration order.
        // New migrations do not need this type of check.
        if ($this->hasTable('CreditNotes')) {
            return;
        }

        $table = $this->table('CreditNotes');
        $this->addTenant($table);
        $table->addColumn('customer', 'integer')
            ->addColumn('name', 'string')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('status', 'string', ['length' => 15])
            ->addColumn('date', 'integer')
            ->addColumn('subtotal', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('total', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('number', 'string', ['length' => 15])
            ->addColumn('draft', 'boolean')
            ->addColumn('viewed', 'boolean')
            ->addColumn('sent', 'boolean')
            ->addColumn('closed', 'boolean')
            ->addColumn('client_id', 'string', ['length' => 24])
            ->addColumn('client_id_exp', 'integer')
            ->addColumn('amount_refunded', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('amount_credited', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('amount_applied_to_invoice', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('balance', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('paid', 'boolean')
            ->addIndex('paid')
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addIndex('viewed')
            ->addTimestamps()
            ->addForeignKey('customer', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('number')
            ->addIndex('sent')
            ->addIndex('client_id', ['unique' => true])
            ->addIndex('client_id_exp')
            ->addIndex('draft')
            ->addIndex('status')
            ->addIndex('date')
            ->create();
    }
}
