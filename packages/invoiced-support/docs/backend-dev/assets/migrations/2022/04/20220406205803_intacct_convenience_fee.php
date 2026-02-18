<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctConvenienceFee extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('write_convenience_fees', 'boolean')
            ->addColumn('convenience_fee_account', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
