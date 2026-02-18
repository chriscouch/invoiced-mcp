<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SubscriptionAddon extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('SubscriptionAddons');
        $this->addTenant($table);
        $table->addColumn('subscription_id', 'integer')
            ->addColumn('plan', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('plan_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('catalog_item', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('catalog_item_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('quantity', 'integer')
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('subscription_id', 'Subscriptions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
