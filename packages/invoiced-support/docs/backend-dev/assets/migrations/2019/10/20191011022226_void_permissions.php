<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class VoidPermissions extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Roles')
            ->addColumn('invoices_void', 'boolean')
            ->addColumn('credit_notes_void', 'boolean')
            ->addColumn('estimates_void', 'boolean')
            ->update();
    }
}
