<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class WePaySyncProfile extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('WePayData')
            ->addColumn('read_cursor', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('last_synced', 'timestamp', ['null' => true, 'default' => null])
            ->update();
    }
}
