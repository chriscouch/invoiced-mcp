<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Estimate extends MultitenantModelMigration
{
    public function change()
    {
        // This is done in order to facilitate the change in migration order.
        // New migrations do not need this type of check.
        if ($this->hasTable('Estimates')) {
            return;
        }

        $table = $this->table('Estimates');
        $this->addTenant($table);
        $table->addColumn('customer', 'integer')
            ->addColumn('name', 'string')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('status', 'string', ['length' => 15])
            ->addColumn('date', 'integer')
            ->addColumn('expiration_date', 'integer', ['null' => true, 'default' => null])
            ->addColumn('payment_terms', 'string', ['length' => 20, 'null' => true])
            ->addColumn('subtotal', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('total', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('deposit', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('deposit_paid', 'boolean')
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('number', 'string', ['length' => 15])
            ->addColumn('purchase_order', 'string', ['null' => true, 'default' => null, 'length' => 12])
            ->addColumn('draft', 'boolean')
            ->addColumn('viewed', 'boolean')
            ->addColumn('sent', 'boolean')
            ->addColumn('closed', 'boolean')
            ->addColumn('approved', 'string', ['length' => 10, 'null' => true, 'default' => null])
            ->addColumn('client_id', 'string', ['length' => 24])
            ->addColumn('client_id_exp', 'integer')
            ->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('approval_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('last_sent', 'integer')
            ->addTimestamps()
            ->addForeignKey('customer', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addIndex(['number'])
            ->addIndex(['sent'])
            ->addindex('client_id', ['unique' => true])
            ->addIndex('client_id_exp')
            ->addIndex('draft')
            ->addIndex('status')
            ->addIndex('date')
            ->addIndex('viewed')
            ->create();
    }
}
