<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DisableIpCheck extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Users')
            ->addColumn('disable_ip_check', 'boolean')
            ->update();
    }
}
