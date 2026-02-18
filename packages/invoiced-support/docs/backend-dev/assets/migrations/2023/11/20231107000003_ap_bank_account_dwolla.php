<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ApBankAccountDwolla extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorBankAccounts')
            ->addColumn('dwolla_funding_source_id', 'string', ['length' => 64, 'null' => true, 'default' => null])
            ->addColumn('dwolla_funding_source_status', 'string', ['length' => 16, 'default' => 'unverified'])
            ->addColumn('dwolla_account_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('default', 'tinyinteger', ['default' => 0])
            ->addColumn('account_number', 'text', ['null' => true, 'default' => null])
            ->addColumn('routing_number', 'string', ['length' => 24, 'null' => true, 'default' => null])
            ->update();

        $this->table('DwollaAccounts')
            ->removeColumn('plaid_id')
            ->update();
    }
}
