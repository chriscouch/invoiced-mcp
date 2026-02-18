<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class SamlRemoveDisableSp extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CompanySamlSettings')
            ->removeColumn('disable_sp_initiated')
            ->update();
    }
}
