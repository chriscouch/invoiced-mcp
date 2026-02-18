<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AllowAdvancePaymentsSetting extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Settings')
            ->addColumn('allow_advance_payments', 'boolean')
            ->update();
    }
}
