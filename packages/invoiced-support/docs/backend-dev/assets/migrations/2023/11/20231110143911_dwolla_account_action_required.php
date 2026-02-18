<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaAccountActionRequired extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('DwollaAccounts')
            ->addColumn('action_required_beneficial_owners', 'boolean')
            ->update();
    }
}
