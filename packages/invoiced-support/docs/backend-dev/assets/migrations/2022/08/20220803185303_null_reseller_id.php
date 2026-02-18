<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NullResellerId extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BillingProfiles')
            ->changeColumn('reseller_id', 'integer', ['null' => true, 'default' => null, 'length' => 30])
            ->update();

        $this->table('Companies')
            ->dropForeignKey('billing_profile_id')
            ->update();

        $this->table('Companies')
            ->addForeignKey('billing_profile_id', 'BillingProfiles', 'id')
            ->update();
    }
}
