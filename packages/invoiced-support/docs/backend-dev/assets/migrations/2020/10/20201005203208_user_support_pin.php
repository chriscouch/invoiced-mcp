<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class UserSupportPin extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Users')
            ->addColumn('support_pin', 'integer', ['null' => true, 'default' => null])
            ->addIndex('support_pin')
            ->update();
    }
}
