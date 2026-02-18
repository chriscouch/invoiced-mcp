<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NotificationSubscriptions extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('NotificationSubscriptions')->drop()->save();
        $table = $this->table('NotificationSubscriptions');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('member_id', 'integer')
            ->addColumn('subscribe', 'boolean')
            ->addIndex(['member_id', 'customer_id'], ['unique' => true])
            // should be used for
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('member_id', 'Members', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
