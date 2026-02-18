<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctTopLevelCustomer extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('customer_top_level', 'boolean', ['default' => true])
            ->update();
    }
}
