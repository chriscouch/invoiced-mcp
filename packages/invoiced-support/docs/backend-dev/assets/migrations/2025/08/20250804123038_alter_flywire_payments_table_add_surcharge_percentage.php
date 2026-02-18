<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AlterFlywirePaymentsTableAddSurchargePercentage extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('FlywirePayments')
            ->addColumn('surcharge_percentage', 'float', ['default' => 0.00])
            ->update();
    }
}
