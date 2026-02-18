<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Template extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Templates');
        $this->addTenant($table);
        $table->addColumn('filename', 'string')
            ->addColumn('enabled', 'boolean')
            ->addColumn('content', 'text')
            ->addTimestamps()
            ->addIndex(['tenant_id', 'filename'], ['unique' => true])
            ->addIndex('enabled')
            ->create();
    }
}
