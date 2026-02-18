<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaAccountEmailRemove extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('DwollaAccounts')
            ->removeColumn('email')
            ->update();
    }
}
