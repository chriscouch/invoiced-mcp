<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class StringResellerId extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Companies')
            ->changeColumn('reseller_id', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
