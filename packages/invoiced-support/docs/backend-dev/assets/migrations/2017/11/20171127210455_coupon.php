<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Coupon extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Coupons', ['id' => 'internal_id']);
        $this->addTenant($table);
        $table->addColumn('id', 'string', ['collation' => 'utf8_bin'])
            ->addColumn('name', 'string')
            ->addColumn('is_percent', 'boolean', ['default' => true])
            ->addColumn('currency', 'string', ['length' => 3, 'null' => true, 'default' => null])
            ->addColumn('value', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('exclusive', 'boolean')
            ->addColumn('expiration_date', 'integer', ['null' => true, 'default' => null])
            ->addColumn('max_redemptions', 'integer')
            ->addColumn('duration', 'integer')
            ->addColumn('archived', 'boolean')
            ->addTimestamps()
            ->addIndex('currency')
            ->addIndex('archived')
            ->create();
    }
}
