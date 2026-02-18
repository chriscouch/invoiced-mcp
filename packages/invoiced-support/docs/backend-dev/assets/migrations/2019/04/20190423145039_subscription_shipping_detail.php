<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SubscriptionShippingDetail extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ShippingDetails')
            ->addColumn('subscription_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('subscription_id', 'Subscriptions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();
    }
}
