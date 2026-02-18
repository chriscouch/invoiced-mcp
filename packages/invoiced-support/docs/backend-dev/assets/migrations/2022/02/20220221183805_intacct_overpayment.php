<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctOverpayment extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('overpayment_location_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('overpayment_department_id', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
