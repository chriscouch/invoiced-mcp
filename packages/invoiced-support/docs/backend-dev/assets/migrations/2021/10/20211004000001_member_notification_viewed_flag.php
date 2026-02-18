<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MemberNotificationViewedFlag extends MultitenantModelMigration
{
    public function change()
    {
        $this->ensureInstant();
        $this->table('Members')
            ->addColumn('notification_viewed', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->update();
        $this->ensureInstantEnd();
    }
}
