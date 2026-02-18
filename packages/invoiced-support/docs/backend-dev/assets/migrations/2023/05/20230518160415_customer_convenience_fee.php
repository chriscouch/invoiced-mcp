<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerConvenienceFee extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('Customers')
            ->addColumn('convenience_fee', 'boolean', ['default' => true])
            ->update();
        $this->ensureInstantEnd();
    }
}
