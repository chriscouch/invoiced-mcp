<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AttachmentParentIdToBigInt extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Attachments')
            ->changeColumn('parent_id', 'biginteger')
            ->update();
    }
}
