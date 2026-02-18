<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('IntacctAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('intacct_company_id', 'string')
            ->addColumn('entity_id', 'string', ['default' => null, 'null' => true])
            ->addColumn('name', 'string', ['null' => true, 'default' => null])
            ->addColumn('user_id', 'string')
            ->addColumn('user_password_enc', 'string', ['length' => 678])
            ->addTimestamps()
            ->create();
    }
}
