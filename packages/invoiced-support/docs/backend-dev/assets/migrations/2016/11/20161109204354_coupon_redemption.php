<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CouponRedemption extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('CouponRedemptions');
        $this->addTenant($table);
        $table->addColumn('parent_type', 'enum', ['values' => ['customer', 'subscription']])
            ->addColumn('parent_id', 'integer')
            ->addColumn('coupon', 'string')
            ->addColumn('coupon_id', 'integer')
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('num_uses', 'integer')
            ->addTimestamps()
            ->addIndex('active')
            ->create();
    }
}
