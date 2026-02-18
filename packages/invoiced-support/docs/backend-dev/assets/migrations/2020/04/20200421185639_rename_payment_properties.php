<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class RenamePaymentProperties extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Payments')
            ->renameColumn('sender_id', 'ach_sender_id')
            ->update();
    }
}
