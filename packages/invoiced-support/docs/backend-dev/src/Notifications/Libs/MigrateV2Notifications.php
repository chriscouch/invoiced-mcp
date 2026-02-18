<?php

namespace App\Notifications\Libs;

use App\Companies\Models\Company;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Models\NotificationEventSetting;
use Doctrine\DBAL\Connection;

class MigrateV2Notifications
{
    public function __construct(private Connection $database)
    {
    }

    public function migrate(Company $company): void
    {
        // check if already migrated
        if ($company->features->has('notifications_v2_default')) {
            return;
        }

        $conversionTable = NotificationEventSetting::CONVERSION_LIST;

        $this->database->beginTransaction();
        $q = $this->database->prepare('INSERT IGNORE INTO NotificationEventSettings
                   (id, tenant_id, member_id, notification_type, frequency)
            select null, m.tenant_id, m.id, :notification_type, :frequency
                from Members m
                         JOIN Users u ON m.user_id = u.id
                         LEFT JOIN Notifications n ON u.id = n.user_id AND n.event = :event
                where m.tenant_id = :tenant
                    and m.notifications = 0
                    AND (n.enabled IS NULL OR n.enabled = 1)');

        $q->bindValue('tenant', $company->id);
        $q->bindValue('frequency', NotificationFrequency::Instant->toInteger());
        foreach ($conversionTable as $conversion) {
            $q->bindValue('notification_type', $conversion[1]->toInteger());
            $q->bindValue('event', $conversion[0]);
            $q->executeStatement();
        }

        // items that we create by default
        $q = $this->database->prepare('INSERT IGNORE INTO NotificationEventSettings
                   (id, tenant_id, member_id, notification_type, frequency)
            select null, m.tenant_id, m.id, :notification_type, :frequency
                from Members m
                where m.tenant_id = :tenant
                    and m.notifications = 0');
        $q->bindValue('tenant', $company->id);
        $q->bindValue('frequency', NotificationFrequency::Instant->toInteger());
        foreach ([NotificationEventType::PaymentPlanApproved, NotificationEventType::ThreadAssigned, NotificationEventType::TaskAssigned] as $conversion) {
            $q->bindValue('notification_type', $conversion->toInteger());
            $q->executeStatement();
        }

        $q = $this->database->prepare('UPDATE Members 
            set notifications = 1, subscribe_all = 1 
            WHERE tenant_id = :tenant AND notifications = 0');
        $q->bindValue('tenant', $company->id);
        $q->executeStatement();
        $this->database->commit();

        $company->features->enable('notifications_v2_default');
    }
}
