<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingSyncProfileIntegration extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AccountingSyncProfiles')
            ->addIndex('tenant_id')
            ->removeIndex(['tenant_id', 'integration'])
            ->update();

        $this->table('AccountingSyncProfiles')
            ->addIndex(['tenant_id', 'integration'], ['unique' => true])
            ->removeIndex('tenant_id')
            ->update();
    }
}
