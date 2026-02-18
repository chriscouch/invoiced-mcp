<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroClaimedId extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Users')
            ->addColumn('xero_claimed_id', 'string', ['default' => null, 'null' => true])
            ->addIndex('xero_claimed_id')
            ->update();
    }
}
