<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NotificationRecipientIndexImprovement extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('NotificationRecipients')
            ->dropForeignKey('tenant_id')
            ->removeIndex(['tenant_id'])
            ->update();
    }
}
