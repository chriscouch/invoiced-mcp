<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaAccount extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('DwollaAccounts')
            ->addColumn('dwolla_customer_id', 'string')
            ->addColumn('tenant_id', 'integer', ['null' => true])
            ->addForeignKey('tenant_id', 'Companies', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addColumn('plaid_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('dwolla_customer_id', ['unique' => true])
            ->create();
    }
}
