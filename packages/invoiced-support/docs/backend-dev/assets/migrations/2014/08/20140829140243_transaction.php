<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Transaction extends MultitenantModelMigration
{
    public function change()
    {
        // This is done in order to facilitate the change in migration order.
        // New migrations do not need this type of check.
        if ($this->hasTable('Transactions')) {
            return;
        }

        $table = $this->table('Transactions');
        $this->addTenant($table);
        $table->addColumn('invoice', 'integer', ['null' => true, 'default' => null])
            ->addColumn('credit_note_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('customer', 'integer')
            ->addColumn('date', 'integer')
            ->addColumn('type', 'enum', ['values' => ['charge', 'payment', 'refund', 'adjustment']])
            ->addColumn('status', 'enum', ['values' => ['succeeded', 'pending', 'failed']])
            ->addColumn('method', 'string', ['length' => 32])
            ->addColumn('gateway', 'string', ['null' => true, 'default' => null])
            ->addColumn('gateway_id', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('parent_transaction', 'integer', ['null' => true, 'default' => null])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('fee', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('sent', 'boolean')
            ->addColumn('client_id', 'string', ['length' => 24])
            ->addColumn('client_id_exp', 'integer')
            ->addColumn('failure_reason', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_source_type', 'enum', ['values' => ['card', 'bank_account', 'sepa_account'], 'null' => true, 'default' => null])
            ->addColumn('payment_source_id', 'integer', ['default' => null, 'null' => true])
            ->addTimestamps()
            ->addForeignKey('customer', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('invoice', 'Invoices', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('credit_note_id', 'CreditNotes', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addIndex(['invoice'])
            ->addIndex(['type'])
            ->addIndex(['status'])
            ->addIndex(['method'])
            ->addIndex(['gateway'])
            ->addIndex(['gateway_id'])
            ->addIndex(['parent_transaction'])
            ->addIndex('date')
            ->addIndex('currency')
            ->addIndex('client_id', ['unique' => true])
            ->addIndex('client_id_exp')
            ->addIndex('updated_at')
            ->create();
    }
}
