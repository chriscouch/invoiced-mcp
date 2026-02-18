<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillingProfile extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BillingProfiles')
            ->addColumn('billing_system', 'enum', ['default' => null, 'null' => true, 'values' => ['invoiced', 'reseller', 'stripe']])
            ->addColumn('stripe_customer', 'string', ['length' => 30, 'null' => true, 'default' => null])
            ->addColumn('invoiced_customer', 'string', ['length' => 30, 'null' => true, 'default' => null])
            ->addColumn('reseller_id', 'string', ['length' => 30])
            ->addColumn('past_due', 'boolean')
            ->addColumn('canceled', 'boolean')
            ->addColumn('canceled_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('canceled_reason', 'string', ['length' => 30])
            ->addColumn('referred_by', 'string', ['length' => 50])
            ->addTimestamps()
            ->addIndex(['stripe_customer'])
            ->addIndex(['invoiced_customer'])
            ->addIndex(['reseller_id'])
            ->create();

        $this->table('Companies')
            ->addColumn('billing_profile_id', 'integer', ['default' => null, 'null' => true])
            ->addForeignKey('billing_profile_id', 'Companies', 'id')
            ->update();
    }
}
