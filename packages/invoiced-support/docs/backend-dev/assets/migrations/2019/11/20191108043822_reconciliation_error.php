<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ReconciliationError extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('ReconciliationErrors');
        $this->addTenant($table);
        $table->addColumn('object', 'string', ['length' => 12])
            ->addColumn('object_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('accounting_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('integration', 'string', ['length' => 18])
            ->addColumn('level', 'enum', ['values' => ['error', 'warning']])
            ->addColumn('message', 'text')
            ->addIndex('object')
            ->addIndex('object_id')
            ->addIndex('accounting_id')
            ->addIndex('integration')
            ->addTimestamps()
            ->create();
    }
}
