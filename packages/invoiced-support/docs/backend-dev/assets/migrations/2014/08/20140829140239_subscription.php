<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Subscription extends MultitenantModelMigration
{
    public function change()
    {
        // This is done in order to facilitate the change in migration order.
        // New migrations do not need this type of check.
        if ($this->hasTable('Subscriptions')) {
            return;
        }

        $table = $this->table('Subscriptions');
        $this->addTenant($table);
        $table->addColumn('customer', 'integer')
            ->addColumn('plan', 'string')
            ->addColumn('plan_id', 'integer')
            ->addColumn('status', 'string', ['length' => 15])
            ->addColumn('start_date', 'integer')
            ->addColumn('renews_next', 'integer', ['null' => true])
            ->addColumn('renewed_last', 'integer', ['null' => true])
            ->addColumn('quantity', 'integer', ['default' => 1])
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addColumn('cycles', 'integer', ['null' => true, 'default' => null])
            ->addColumn('num_invoices', 'integer')
            ->addColumn('client_id', 'string', ['length' => 24])
            ->addColumn('client_id_exp', 'integer')
            ->addColumn('taxes', 'string')
            ->addColumn('recurring_total', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('mrr', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('snap_to_nth_day', 'integer', ['null' => true, 'default' => null])
            ->addColumn('period_offset_days', 'integer')
            ->addColumn('contract_renewal_mode', 'enum', ['values' => ['auto', 'manual', 'renew_once', 'none'], 'default' => 'none'])
            ->addColumn('contract_renewal_cycles', 'integer', ['null' => true, 'default' => null])
            ->addColumn('contract_period_start', 'integer', ['null' => true, 'default' => null])
            ->addColumn('contract_period_end', 'integer', ['null' => true, 'default' => null])
            ->addColumn('cancel_at_period_end', 'boolean')
            ->addColumn('canceled_at', 'integer', ['null' => true, 'default' => null])
            ->addColumn('canceled', 'boolean')
            ->addColumn('finished', 'boolean')
            ->addColumn('pending_renewal', 'boolean')
            ->addColumn('payment_source_type', 'enum', ['values' => ['card', 'bank_account', 'sepa_account'], 'null' => true, 'default' => null])
            ->addColumn('payment_source_id', 'integer', ['default' => null, 'null' => true])
            ->addTimestamps()
            ->addForeignKey('customer', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addindex('client_id', ['unique' => true])
            ->addIndex('plan')
            ->addIndex('finished')
            ->addIndex('canceled')
            ->addIndex('cycles')
            ->addIndex('renews_next')
            ->addIndex('status')
            ->addIndex('pending_renewal')
            ->addIndex('contract_period_end')
            ->addIndex('client_id_exp')
            ->create();
    }
}
