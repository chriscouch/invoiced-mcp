<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillingProfileName extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BillingProfiles')
            ->addColumn('name', 'string')
            ->update();
    }
}
