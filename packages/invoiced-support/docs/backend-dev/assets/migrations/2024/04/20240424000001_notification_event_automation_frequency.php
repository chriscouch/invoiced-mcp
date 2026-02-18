<?php

use App\Core\Multitenant\MultitenantModelMigration;
use App\Notifications\Enums\NotificationEventType;

final class NotificationEventAutomationFrequency extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute('INSERT IGNORE INTO NotificationEventCompanySettings 
            SELECT null, tenant_id, '.NotificationEventType::AutomationTriggered->toInteger().', 1
            FROM NotificationEventCompanySettings GROUP BY tenant_id');

        $this->execute('INSERT IGNORE INTO NotificationEventSettings 
            SELECT null, tenant_id, member_id, '.NotificationEventType::AutomationTriggered->toInteger().', 1
            FROM NotificationEventSettings GROUP BY member_id');
    }
}
