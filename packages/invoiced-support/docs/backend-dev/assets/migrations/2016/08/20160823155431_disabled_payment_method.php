<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DisabledPaymentMethod extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('DisabledPaymentMethods');
        $this->addTenant($table);
        $table->addColumn('object_type', 'enum', ['values' => ['customer', 'invoice', 'plan', 'estimate']])
            ->addColumn('object_id', 'string')
            ->addColumn('method', 'string', ['length' => 32])
            ->addTimestamps()
            ->addIndex('object_type')
            ->addIndex('object_id')
            ->create();
    }
}
