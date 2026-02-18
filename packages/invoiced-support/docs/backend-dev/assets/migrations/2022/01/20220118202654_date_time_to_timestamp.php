<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DateTimeToTimestamp extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();
        $this->table('ScheduledSends')
            ->changeColumn('send_after', 'timestamp', ['null' => true, 'default' => null])
            ->changeColumn('sent_at', 'timestamp', ['null' => true, 'default' => null])
            ->update();
        $this->table('InvoiceDeliveries')
            ->changeColumn('last_sent_email', 'timestamp', ['null' => true, 'default' => null])
            ->changeColumn('last_sent_sms', 'timestamp', ['null' => true, 'default' => null])
            ->changeColumn('last_sent_letter', 'timestamp', ['null' => true, 'default' => null])
            ->update();
        $this->table('CustomerPortalEvents')
            ->changeColumn('timestamp', 'timestamp')
            ->update();
        $this->table('LateFeeSchedules')
            ->changeColumn('last_run', 'timestamp', ['null' => true, 'default' => null])
            ->update();
        $this->table('Members')
            ->changeColumn('notification_viewed', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->update();
    }
}
