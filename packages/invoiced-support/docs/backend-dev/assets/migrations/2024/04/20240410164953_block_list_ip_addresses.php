<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BlockListIpAddresses extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BlockListIpAddresses')
            ->addColumn('ip', 'string', ['length' => 45])
            ->addTimestamps()
            ->addIndex('ip', ['unique' => true])
            ->create();
    }
}
