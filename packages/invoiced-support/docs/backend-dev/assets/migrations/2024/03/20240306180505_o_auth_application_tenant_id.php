<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class OAuthApplicationTenantId extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('OAuthApplications')
            ->addColumn('tenant_id', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
