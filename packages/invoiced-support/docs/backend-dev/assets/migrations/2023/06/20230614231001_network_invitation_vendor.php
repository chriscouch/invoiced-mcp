<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NetworkInvitationVendor extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('NetworkInvitations')
            ->addColumn('vendor_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('vendor_id', 'Vendors', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->update();
    }
}
