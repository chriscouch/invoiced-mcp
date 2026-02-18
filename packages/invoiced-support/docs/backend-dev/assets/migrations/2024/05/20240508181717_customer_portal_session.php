<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerPortalSession extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('CustomerPortalSessions');
        $this->addTenant($table);
        $table->addColumn('identifier', 'string', ['length' => 32])
            ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('email', 'string', ['null' => true, 'default' => null])
            ->addColumn('expires', 'timestamp')
            ->addIndex('identifier', ['unique' => true])
            ->addIndex('expires')
            ->create();
    }
}
