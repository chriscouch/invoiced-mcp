<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveSyncAllEntitiesFromIntacctAccounts extends MultitenantModelMigration
{
    public function change(): void
    {
        $intactAccountsTable = $this->table('IntacctAccounts');

        if ($intactAccountsTable->hasColumn('sync_all_entities')) {
            $intactAccountsTable->removeColumn('sync_all_entities')
                ->update();
        }
    }
}