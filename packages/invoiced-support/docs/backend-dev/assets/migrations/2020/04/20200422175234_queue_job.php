<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QueueJob extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('QueueJobs', ['id' => false, 'primary_key' => ['id']]);
        $this->addTenant($table);
        $table->addColumn('id', 'string', ['length' => 32])
            ->addColumn('name', 'string')
            ->addColumn('object_name', 'string')
            ->addColumn('object_type_id', 'integer')
            ->addColumn('object_id', 'integer')
            ->addColumn('status', 'enum', ['values' => ['succeeded', 'failed', 'running', 'created'], 'default' => 'created'])
            ->addColumn('parameters', 'text')
            ->addColumn('error_log', 'text')
            ->addTimestamps()
            ->addIndex('id', ['unique' => true])
            ->addIndex(['tenant_id', 'object_id', 'object_type_id'])
            ->create();
    }
}
