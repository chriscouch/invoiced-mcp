<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class StringResellerId2 extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BillingProfiles')
            ->changeColumn('reseller_id', 'string', ['null' => true, 'default' => null, 'length' => 30])
            ->update();
    }
}
