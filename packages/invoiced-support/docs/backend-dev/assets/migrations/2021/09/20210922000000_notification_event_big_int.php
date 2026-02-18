<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NotificationEventBigInt extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('NotificationEvents')
            ->changeColumn('object_id', 'biginteger')
            ->update();
    }
}
