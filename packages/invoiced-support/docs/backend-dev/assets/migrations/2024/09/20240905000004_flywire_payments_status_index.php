<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywirePaymentsStatusIndex extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('FlywirePayments')
            ->addIndex(['status', 'tenant_id', 'updated_at'])
            ->update();
    }
}
