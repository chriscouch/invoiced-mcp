<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NotificationEventCompanySettings extends MultitenantModelMigration
{
    public function change()
    {
        $this->ensureInstant();
        $table = $this->table('Members');
        $table->addColumn('allow_notification_selections', 'boolean')
            ->update();
        $this->ensureInstantEnd();

        $table = $this->table('NotificationEventCompanySettings');
        $this->addTenant($table);
        $table
            // NotificationEvent::EVENTS
            ->addColumn('notification_type', 'smallinteger')
            // NotificationEventSetting::FREQUENCIES
            ->addColumn('frequency', 'smallinteger')
            ->addIndex(['tenant_id', 'notification_type'], ['unique' => true])
            ->create();
    }
}
