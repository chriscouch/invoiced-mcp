<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddPausedColumn extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Subscriptions')
            ->addColumn('paused', 'boolean', ['default' => false])
            ->addIndex(['paused'])
            ->update();
    }
}
