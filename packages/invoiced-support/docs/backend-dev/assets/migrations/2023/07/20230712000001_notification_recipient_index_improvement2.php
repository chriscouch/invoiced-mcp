<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NotificationRecipientIndexImprovement2 extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('NotificationRecipients')
            ->addIndex(['member_id', 'sent'])
            ->addIndex(['notification_event_id', 'member_id'])
            ->update();
    }
}
