<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CompanyNickname extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Companies')
            ->addColumn('nickname', 'string', ['default' => null, 'null' => true])
            ->update();
    }
}
