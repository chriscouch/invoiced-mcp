<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class WePayDataMccUpdate extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('WePayData')
            ->changeColumn('mcc', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
