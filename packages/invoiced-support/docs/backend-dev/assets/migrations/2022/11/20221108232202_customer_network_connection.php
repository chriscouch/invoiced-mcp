<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerNetworkConnection extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Customers')
            ->addColumn('network_connection_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('network_connection_id', 'NetworkConnections', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->update();
    }
}
