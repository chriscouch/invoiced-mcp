<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NotificationRecipientIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('NotificationRecipients')
            ->addIndex('sent')
            ->update();
    }
}
