<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MoreCustomerTypes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('Customers')
            ->changeColumn('type', 'enum', ['values' => ['company', 'person', 'government', 'non_profit']])
            ->update();
        $this->ensureInstantEnd();
    }
}
