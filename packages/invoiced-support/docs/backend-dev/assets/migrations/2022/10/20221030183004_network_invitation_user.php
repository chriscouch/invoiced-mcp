<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NetworkInvitationUser extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('NetworkInvitations')
            ->addColumn('sent_by_user_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('sent_by_user_id', 'Members', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->update();

        $this->table('NetworkConnections')
            ->removeColumn('from_company_id')
            ->update();
    }
}
