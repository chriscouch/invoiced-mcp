<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PlaidBankAccountNeedsUpdate extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PlaidBankAccountLinks')
            ->addColumn('needs_update', 'boolean')
            ->update();
    }
}
