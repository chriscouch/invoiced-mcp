<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentMethod extends MultitenantModelMigration
{
    public function change()
    {
        // This is done in order to facilitate the change in migration order.
        // New migrations do not need this type of check.
        if ($this->hasTable('PaymentMethods')) {
            return;
        }

        $table = $this->table('PaymentMethods', ['id' => false, 'primary_key' => ['tenant_id', 'id']]);
        $this->addTenant($table);
        $table->addColumn('id', 'string', ['length' => 32])
            ->addColumn('enabled', 'boolean')
            ->addColumn('gateway', 'string', ['null' => true, 'default' => null])
            ->addColumn('meta', 'string', ['length' => 2500, 'null' => true, 'default' => null])
            ->addColumn('min', 'integer', ['null' => true, 'default' => null])
            ->addColumn('max', 'integer', ['null' => true, 'default' => null])
            ->addColumn('merchant_account_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('name', 'string', ['default' => null, 'null' => true])
            ->addColumn('order', 'integer')
            ->addIndex('order')
            ->addIndex('merchant_account_id')
            ->addTimestamps()
            ->create();
    }
}
