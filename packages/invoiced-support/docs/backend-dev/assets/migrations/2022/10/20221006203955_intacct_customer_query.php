<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctCustomerQuery extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('customer_read_query_addon', 'string', ['default' => null, 'null' => true])
            ->update();
    }
}
