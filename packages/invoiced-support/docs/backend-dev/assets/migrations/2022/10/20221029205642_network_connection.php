<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NetworkConnection extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('NetworkConnections');
        $this->addTenant($table);
        $table->addColumn('from_company_id', 'integer')
            ->addColumn('connected_to_id', 'integer')
            ->addColumn('is_customer', 'boolean')
            ->addColumn('is_vendor', 'boolean')
            ->addTimestamps()
            ->addForeignKey('connected_to_id', 'Companies', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->create();
    }
}
