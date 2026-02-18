<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NotificationEventMessage extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('NotificationEvents')
            ->addColumn('message', 'string', ['null' => true, 'default' => null, 'length' => 1024])
            ->update();
    }
}
