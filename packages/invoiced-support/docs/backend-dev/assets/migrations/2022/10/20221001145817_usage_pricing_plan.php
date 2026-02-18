<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class UsagePricingPlan extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('UsagePricingPlans')
            ->addColumn('tenant_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('billing_profile_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('tenant_id', 'Companies', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->addForeignKey('billing_profile_id', 'Companies', 'id')
            ->addColumn('usage_type', 'integer')
            ->addColumn('threshold', 'integer')
            ->addColumn('unit_price', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addIndex('threshold')
            ->addIndex(['tenant_id', 'billing_profile_id', 'usage_type'], ['unique' => true])
            ->addTimestamps()
            ->create();
    }
}
