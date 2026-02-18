<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NetworkInvitationCustomer extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('NetworkInvitations')
            ->addColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('customer_id', 'Customers', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->update();
    }
}
