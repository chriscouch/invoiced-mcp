<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AlterIntacctAccountsSyncAllEntities extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('IntacctAccounts')
            ->addColumn('sync_all_entities', 'boolean')
            ->update();
    }
}