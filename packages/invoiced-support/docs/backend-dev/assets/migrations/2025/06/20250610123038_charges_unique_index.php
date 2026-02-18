<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ChargesUniqueIndex extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Charges')
            ->addIndex(['gateway_id', 'gateway', 'tenant_id'], ['unique' => true])
            ->update();
    }
}
