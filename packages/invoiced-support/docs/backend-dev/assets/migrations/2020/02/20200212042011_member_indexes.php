<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MemberIndexes extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Members')
            ->removeIndex(['expires'])
            ->addIndex(['tenant_id', 'user_id', 'expires'])
            ->addIndex(['tenant_id', 'expires'])
            ->update();
    }
}
