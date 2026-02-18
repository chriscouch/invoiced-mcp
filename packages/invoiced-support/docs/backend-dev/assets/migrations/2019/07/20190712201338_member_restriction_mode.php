<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MemberRestrictionMode extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Members')
            ->addColumn('restriction_mode', 'enum', ['values' => ['none', 'custom_field', 'owner'], 'default' => 'none'])
            ->update();
    }
}
