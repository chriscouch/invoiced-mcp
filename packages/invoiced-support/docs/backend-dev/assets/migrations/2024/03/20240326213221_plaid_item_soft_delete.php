<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PlaidItemSoftDelete extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PlaidBankAccountLinks')
            ->addColumn('deleted', 'boolean')
            ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
            ->update();
    }
}
