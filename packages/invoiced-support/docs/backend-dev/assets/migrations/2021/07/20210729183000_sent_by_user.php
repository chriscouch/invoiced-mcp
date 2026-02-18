<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SentByUser extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('InboxEmails')
            ->addColumn('sent_by_id', 'integer', ['null' => true, 'default' => null])
            ->update();

        $this->table('Emails')
            ->addColumn('sent_by_id', 'integer', ['null' => true, 'default' => null])
            ->update();

        $this->table('Letters')
            ->addColumn('sent_by_id', 'integer', ['null' => true, 'default' => null])
            ->update();

        $this->table('TextMessages')
            ->addColumn('sent_by_id', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
