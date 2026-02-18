<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaAccountStatus extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('DwollaAccounts')
            ->addColumn('status', 'string', ['length' => 16, 'default' => 'unverified'])
            ->update();
    }
}
