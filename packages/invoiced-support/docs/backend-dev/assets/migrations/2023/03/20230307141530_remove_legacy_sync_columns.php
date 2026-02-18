<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveLegacySyncColumns extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('IntacctSyncProfiles')
            ->removeColumn('auto_sync')
            ->removeColumn('auto_import')
            ->removeColumn('next_sync')
            ->removeColumn('next_import')
            ->update();
        $this->table('QuickBooksOnlineSyncProfiles')
            ->removeColumn('auto_sync')
            ->removeColumn('auto_import')
            ->removeColumn('next_sync')
            ->removeColumn('next_import')
            ->update();
        $this->table('XeroSyncProfiles')
            ->removeColumn('auto_sync')
            ->removeColumn('next_sync')
            ->update();
    }
}
