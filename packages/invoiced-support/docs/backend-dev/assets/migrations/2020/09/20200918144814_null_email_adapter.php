<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NullEmailAdapter extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Settings')
            ->changeColumn('email_provider', 'enum', ['values' => ['invoiced', 'smtp', 'null'], 'default' => 'invoiced'])
            ->update();
    }
}
