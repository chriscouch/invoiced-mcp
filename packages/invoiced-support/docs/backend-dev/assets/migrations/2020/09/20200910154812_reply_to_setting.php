<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ReplyToSetting extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Settings')
            ->addColumn('reply_to_inbox_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('reply_to_inbox_id', 'Inboxes', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
