<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BlockListEmailAddresses extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BlockListEmailAddresses')
            ->addColumn('email', 'string')
            ->addColumn('reason', 'smallinteger')
            ->addColumn('complaint_count', 'integer')
            ->addTimestamps()
            ->addIndex('email', ['unique' => true])
            ->create();
    }
}
