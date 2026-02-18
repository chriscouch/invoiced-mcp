<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueBillingProfile extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BillingProfiles')
            ->removeColumn('reseller_id')
            ->removeColumn('canceled')
            ->removeColumn('canceled_at')
            ->removeColumn('canceled_reason')
            ->removeIndex('stripe_customer')
            ->changeColumn('stripe_customer', 'string', ['null' => true, 'default' => null])
            ->addIndex('stripe_customer', ['unique' => true])
            ->removeIndex('invoiced_customer')
            ->changeColumn('invoiced_customer', 'string', ['null' => true, 'default' => null])
            ->addIndex('invoiced_customer', ['unique' => true])
            ->update();
    }
}
