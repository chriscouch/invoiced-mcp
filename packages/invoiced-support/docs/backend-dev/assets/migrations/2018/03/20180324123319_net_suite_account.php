<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NetSuiteAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('NetSuiteAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('name', 'string', ['null' => true, 'default' => null])
            ->addColumn('account_id', 'string')
            ->addColumn('subsidiary_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('consumer_key_enc', 'string', ['length' => 678])
            ->addColumn('consumer_secret_enc', 'string', ['length' => 678])
            ->addColumn('token_enc', 'string', ['length' => 678])
            ->addColumn('token_secret_enc', 'string', ['length' => 678])
            ->addTimestamps()
            ->create();
    }
}
