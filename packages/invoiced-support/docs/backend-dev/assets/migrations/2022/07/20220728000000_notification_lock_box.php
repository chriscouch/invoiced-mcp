<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NotificationLockBox extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute('SET SESSION max_statement_time=0');
        $this->execute('INSERT IGNORE INTO NotificationEventCompanySettings (SELECT NULL, tenant_id, 16, 1 FROM NotificationEventCompanySettings GROUP BY tenant_id)');
    }
}
