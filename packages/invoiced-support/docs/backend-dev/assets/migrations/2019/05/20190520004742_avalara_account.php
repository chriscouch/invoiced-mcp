<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AvalaraAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('AvalaraAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('company_code', 'string')
            ->addColumn('license_key_enc', 'string', ['length' => 678])
            ->addColumn('account_id', 'string')
            ->addColumn('commit_mode', 'enum', ['values' => ['disable', 'uncommitted', 'committed'], 'default' => 'uncommitted'])
            ->addTimestamps()
            ->create();
    }
}
