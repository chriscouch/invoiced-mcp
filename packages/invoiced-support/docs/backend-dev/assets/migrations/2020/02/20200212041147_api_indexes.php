<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ApiIndexes extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ApiKeys')
            ->removeIndex(['source'])
            ->addIndex(['tenant_id', 'protected', 'source', 'expires', 'user_id'])
            ->addIndex(['secret_hash', 'expires'])
            ->update();
    }
}
