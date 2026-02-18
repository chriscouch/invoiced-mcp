<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PlaidBankAccountLink extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('PlaidBankAccountLinks');
        $this->addTenant($table);
        $table->addColumn('item_id', 'string')
            ->addColumn('access_token_enc', 'string', ['length' => 678])
            ->addColumn('institution_name', 'string', ['null' => true, 'default' => null])
            ->addColumn('institution_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('account_id', 'string')
            ->addColumn('account_name', 'string', ['null' => true, 'default' => null])
            ->addColumn('account_last4', 'string', ['length' => 4, 'null' => true, 'default' => null])
            ->addColumn('account_type', 'string', ['null' => true, 'default' => null])
            ->addColumn('account_subtype', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
