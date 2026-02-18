<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Export extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Exports');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('status', 'enum', ['values' => ['succeeded', 'pending', 'failed']])
            ->addColumn('total_records', 'integer', ['null' => true, 'default' => null])
            ->addColumn('message', 'string')
            ->addColumn('position', 'integer')
            ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('type', 'string')
            ->addColumn('download_url', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('type')
            ->addIndex('user_id')
            ->create();
    }
}
