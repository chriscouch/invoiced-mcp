<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NetworkChangeExpiresAt extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('NetworkInvitations')
            ->removeColumn('expires_at')
            ->update();
        $this->table('NetworkInvitations')
            ->addColumn('expires_at', 'timestamp', ['update' => ''])
            ->update();
    }
}
