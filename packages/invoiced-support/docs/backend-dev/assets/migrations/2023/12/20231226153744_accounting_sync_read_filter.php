<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingSyncReadFilter extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AccountingSyncReadFilters');
        $this->addTenant($table);
        $table->addColumn('integration', 'tinyinteger')
            ->addColumn('object_type', 'string')
            ->addColumn('formula', 'text')
            ->addColumn('enabled', 'boolean')
            ->addTimestamps()
            ->addIndex(['tenant_id', 'integration', 'object_type'])
            ->create();
    }
}
