<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RoutingNumberInfo extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('RoutingNumbers')
            ->addColumn('routing_number', 'string', ['length' => 9])
            ->addColumn('bank_name', 'string')
            ->addTimestamps()
            ->addIndex('routing_number', ['unique' => true])
            ->create();
    }
}
