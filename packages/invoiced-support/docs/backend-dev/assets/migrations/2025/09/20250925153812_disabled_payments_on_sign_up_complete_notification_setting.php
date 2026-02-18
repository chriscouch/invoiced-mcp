<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;
use App\Notifications\Enums\NotificationEventType;

final class DisabledPaymentsOnSignUpCompleteNotificationSetting extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('INSERT IGNORE INTO NotificationEventCompanySettings 
            SELECT null, tenant_id, '.NotificationEventType::DisabledMethodsOnSignUpPageCompleted->toInteger().', 1
            FROM NotificationEventCompanySettings GROUP BY tenant_id');

        $this->execute('INSERT IGNORE INTO NotificationEventSettings 
            SELECT null, tenant_id, member_id, '.NotificationEventType::DisabledMethodsOnSignUpPageCompleted->toInteger().', 1
            FROM NotificationEventSettings GROUP BY member_id');
    }
}