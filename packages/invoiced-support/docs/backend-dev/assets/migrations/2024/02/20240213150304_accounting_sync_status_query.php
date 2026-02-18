<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingSyncStatusQuery extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AccountingSyncStatuses')
            ->addColumn('query', 'string', ['null' => true, 'default' => null, 'length' => 10000])
            ->update();
    }
}
