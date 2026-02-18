<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class RoleAp extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Roles')
            ->addColumn('bills_create', 'boolean')
            ->addColumn('bills_edit', 'boolean')
            ->addColumn('bills_delete', 'boolean')
            ->addColumn('vendor_payments_create', 'boolean')
            ->addColumn('vendor_payments_edit', 'boolean')
            ->addColumn('vendor_payments_delete', 'boolean')
            ->addColumn('vendors_create', 'boolean')
            ->addColumn('vendors_edit', 'boolean')
            ->addColumn('vendors_delete', 'boolean')
            ->update();
    }
}
