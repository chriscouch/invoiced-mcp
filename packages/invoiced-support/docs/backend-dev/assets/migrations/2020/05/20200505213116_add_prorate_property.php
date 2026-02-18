<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddProrateProperty extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Subscriptions')
            ->addColumn('prorate', 'boolean', ['default' => 1])
            ->update();
    }
}
