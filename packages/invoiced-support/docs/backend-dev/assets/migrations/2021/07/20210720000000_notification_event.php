<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NotificationEvent extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('NotificationEvents');
        $this->addTenant($table);
        $table->addColumn('type', 'smallinteger')
            ->addTimestamps()
            ->addColumn('object_id', 'integer')
            // should be used for
            ->addIndex('created_at')
            ->create();

        $table = $this->table('NotificationRecipients');
        $this->addTenant($table);
        $table->addColumn('notification_event_id', 'integer')
            ->addColumn('member_id', 'integer')
            ->addColumn('sent', 'boolean')
            // should be used for
            ->addForeignKey('notification_event_id', 'NotificationEvents', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('member_id', 'Members', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $table = $this->table('Members');
        $table->addColumn('notifications', 'boolean', ['default' => false])
            ->addColumn('subscribe_all', 'boolean', ['default' => true])
            ->update();

        $table = $this->table('NotificationSubscriptions', ['id' => false, 'primary_key' => ['customer_id', 'member_id']]);
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('member_id', 'integer')
            ->addColumn('subscribe', 'boolean')
            // should be used for
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('member_id', 'Members', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
