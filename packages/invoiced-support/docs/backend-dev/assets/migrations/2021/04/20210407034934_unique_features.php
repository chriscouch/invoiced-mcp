<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueFeatures extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Features')
            ->addIndex(['tenant_id', 'feature'], ['unique' => true])
            ->update();
    }
}
