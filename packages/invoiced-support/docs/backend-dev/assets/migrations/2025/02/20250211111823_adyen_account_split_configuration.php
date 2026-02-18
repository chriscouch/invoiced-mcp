<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenAccountSplitConfiguration extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AdyenAccounts')
            ->addColumn('split_configuration_id', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
