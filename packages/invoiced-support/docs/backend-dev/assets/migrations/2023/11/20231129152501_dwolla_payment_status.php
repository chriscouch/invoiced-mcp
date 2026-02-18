<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaPaymentStatus extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('DwollaPayments')
            ->changeColumn('status', 'string')
            ->update();
    }
}
