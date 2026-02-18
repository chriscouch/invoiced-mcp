<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Import extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Imports');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('status', 'enum', ['values' => ['succeeded', 'pending', 'failed']])
            ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('type', 'string')
            ->addColumn('source_file', 'string', ['null' => true, 'default' => null])
            ->addColumn('total_records', 'integer', ['null' => true, 'default' => null])
            ->addColumn('message', 'string')
            ->addColumn('position', 'integer')
            ->addColumn('num_imported', 'integer')
            ->addColumn('num_updated', 'integer')
            ->addColumn('num_failed', 'integer')
            ->addColumn('failure_detail', 'text', ['limit' => Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM])
            ->addTimestamps()
            ->addIndex('type')
            ->addIndex('user_id')
            ->create();
    }
}
