<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NotificationEventSetting extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('NotificationEventSettings');
        $this->addTenant($table);
        $table->addColumn('member_id', 'integer')
            // NotificationEvent::EVENTS
            ->addColumn('notification_type', 'smallinteger')
            // NotificationEventSetting::FREQUENCIES
            ->addColumn('frequency', 'smallinteger')
            ->addIndex(['member_id', 'notification_type'], ['unique' => true])
            ->addForeignKey('member_id', 'Members', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
