<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingSyncFieldMapping extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AccountingSyncFieldMappings');
        $this->addTenant($table);
        $table->addColumn('integration', 'tinyinteger')
            ->addColumn('object_type', 'string')
            ->addColumn('source_field', 'string')
            ->addColumn('destination_field', 'string')
            ->addColumn('data_type', 'string')
            ->addColumn('enabled', 'boolean')
            ->addTimestamps()
            ->addIndex(['tenant_id', 'integration', 'object_type'])
            ->create();
    }
}
