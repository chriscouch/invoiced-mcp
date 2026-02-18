<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillingProfileBillingInterval extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BillingProfiles')
            ->addColumn('billing_interval', 'tinyinteger', ['null' => true, 'default' => null])
            ->update();
    }
}
