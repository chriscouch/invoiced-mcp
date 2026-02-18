<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class SyncStatus extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AccountingSyncStatuses');
        $this->addTenant($table);
        $table->addColumn('integration_id', 'integer', ['length' => 3, 'signed' => false])
            ->addColumn('started_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('finished_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('message', 'string', ['null' => true, 'default' => null])
            ->addColumn('last_updated_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('running', 'boolean')
            ->addIndex(['tenant_id', 'integration_id'], ['unique' => true])
            ->create();
    }
}
