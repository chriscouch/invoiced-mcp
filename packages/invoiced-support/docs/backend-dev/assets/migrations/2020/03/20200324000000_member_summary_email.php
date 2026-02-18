<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MemberSummaryEmail extends MultitenantModelMigration
{
    public function change()
    {
        // apply new field with default value for existing users
        $this->table('Members')
            ->addColumn('email_update_frequency', 'string', ['length' => 5, 'null' => true, 'default' => null])
            ->update();
    }
}
