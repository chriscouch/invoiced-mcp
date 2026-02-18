<?php

use Phinx\Migration\AbstractMigration;

final class GroupMember extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('GroupMembers', ['id' => false, 'primary_key' => ['group', 'user_id']]);
        $table->addColumn('group', 'string')
            ->addColumn('user_id', 'integer')
            ->addTimestamps()
            ->create();
    }
}
