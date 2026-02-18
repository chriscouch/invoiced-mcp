<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MemberRestrictions extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Members')
            ->addColumn('restrictions', 'text', ['null' => true, 'default' => null])
            ->update();
    }
}
