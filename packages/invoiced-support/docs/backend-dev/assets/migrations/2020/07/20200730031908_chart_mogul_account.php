<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChartMogulAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('ChartMogulAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('token', 'string')
            ->addColumn('secret', 'string', ['length' => 678])
            ->addColumn('enabled', 'boolean')
            ->addColumn('data_source', 'string', ['null' => true, 'default' => null])
            ->addColumn('sync_cursor', 'integer')
            ->addColumn('last_sync_attempt', 'integer', ['null' => true, 'default' => null])
            ->addColumn('last_sync_error', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
