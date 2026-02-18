<?php

use App\Core\Multitenant\MultitenantModelMigration;
use Phinx\Db\Adapter\AdapterInterface;

final class EmailThreadNotesAutoincrement extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('EmailThreadNotes', ['id' => false, 'primary_key' => ['id']]);
        $table->changeColumn('id', AdapterInterface::PHINX_TYPE_BIG_INTEGER, ['identity' => true])
            ->update();
    }
}
