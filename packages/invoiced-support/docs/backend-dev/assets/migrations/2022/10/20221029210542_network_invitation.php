<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NetworkInvitation extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('NetworkInvitations')
            ->addColumn('uuid', 'string')
            ->addColumn('email', 'string', ['null' => true, 'default' => null])
            ->addColumn('from_company_id', 'integer')
            ->addColumn('to_company_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('is_customer', 'boolean')
            ->addColumn('expires_at', 'timestamp')
            ->addColumn('declined', 'boolean')
            ->addColumn('declined_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('from_company_id', 'Companies', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('to_company_id', 'Companies', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addIndex('uuid')
            ->create();
    }
}
