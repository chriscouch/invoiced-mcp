<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroConvFee extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('XeroSyncProfiles')
            ->addColumn('write_convenience_fees', 'boolean')
            ->update();
    }
}
