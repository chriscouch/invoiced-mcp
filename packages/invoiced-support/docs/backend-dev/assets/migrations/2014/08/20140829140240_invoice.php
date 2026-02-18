<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Invoice extends MultitenantModelMigration
{
    public function change()
    {
        // This is done in order to facilitate the change in migration order.
        // New migrations do not need this type of check.
        if ($this->hasTable('Invoices')) {
            return;
        }

        $table = $this->table('Invoices');
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
            ->addColumn('payment_terms', 'string', ['length' => 20, 'null' => true, 'default' => null])
            ->addColumn('due_date', 'integer', ['null' => true, 'default' => null])
            ->addColumn('date_bad_debt', 'integer', ['null' => true, 'default' => null])
            ->addColumn('date_paid', 'integer', ['null' => true, 'default' => null])
            ->addColumn('amount_paid', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('amount_credited', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('balance', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('chase', 'boolean')
            ->addColumn('next_chase_on', 'integer', ['null' => true, 'default' => null])
            ->addColumn('recalculate_chase', 'boolean', ['default' => true])
            ->addColumn('last_sent', 'integer')
            ->addColumn('subscription_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('purchase_order', 'string', ['length' => 12, 'null' => true, 'default' => null])
            ->addColumn('draft', 'boolean')
            ->addColumn('viewed', 'boolean')
            ->addColumn('sent', 'boolean')
            ->addColumn('paid', 'boolean')
            ->addColumn('closed', 'boolean')
            ->addColumn('client_id', 'string', ['length' => 24])
            ->addColumn('client_id_exp', 'integer')
            ->addColumn('collection_mode', 'enum', ['values' => ['auto', 'manual'], 'default' => 'manual'])
            ->addColumn('autopay', 'boolean')
            ->addColumn('attempt_count', 'integer')
            ->addColumn('next_payment_attempt', 'integer', ['null' => true, 'default' => null])
            ->addColumn('payment_plan_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('payment_source_type', 'enum', ['values' => ['card', 'bank_account', 'sepa_account'], 'null' => true, 'default' => null])
            ->addColumn('payment_source_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('next_chase_step', 'enum', ['values' => ['email', 'flag', 'sms'], 'default' => null, 'null' => true])
            ->addColumn('needs_attention', 'boolean')
            ->addIndex('needs_attention')
            ->addTimestamps()
            ->addForeignKey('customer', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('number')
            ->addIndex('sent')
            ->addIndex('paid')
            ->addIndex('closed')
            ->addIndex('draft')
            ->addIndex('status')
            ->addIndex('subscription_id')
            ->addIndex('date')
            ->addIndex('currency')
            ->addIndex('viewed')
            ->addIndex('client_id', ['unique' => true])
            ->addIndex('client_id_exp')
            ->addIndex('recalculate_chase')
            ->addIndex('collection_mode')
            ->addIndex('autopay')
            ->addIndex('updated_at')
            ->create();
    }
}
